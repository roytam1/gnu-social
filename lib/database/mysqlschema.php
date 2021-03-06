<?php
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database schema for MariaDB
 *
 * @category  Database
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

/**
 * Class representing the database schema for MariaDB
 *
 * A class representing the database schema. Can be used to
 * manipulate the schema -- especially for plugins and upgrade
 * utilities.
 *
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class MysqlSchema extends Schema
{
    public static $_single = null;

    /**
     * Main public entry point. Use this to get
     * the singleton object.
     *
     * @param object|null $conn
     * @param string|null dummy param
     * @return Schema the (single) Schema object
     */
    public static function get($conn = null, $_ = 'mysql')
    {
        if (empty(self::$_single)) {
            self::$_single = new Schema($conn, 'mysql');
        }
        return self::$_single;
    }

    /**
     * Returns a TableDef object for the table
     * in the schema with the given name.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $table Name of the table to get
     *
     * @return array of tabledef for that table.
     * @throws PEAR_Exception
     * @throws SchemaTableMissingException
     */
    public function getTableDef($table)
    {
        $def = [];
        $hasKeys = false;

        // Pull column data from INFORMATION_SCHEMA
        $columns = $this->fetchMetaInfo($table, 'COLUMNS', 'ORDINAL_POSITION');
        if (count($columns) == 0) {
            throw new SchemaTableMissingException("No such table: $table");
        }

        foreach ($columns as $row) {
            $name = $row['COLUMN_NAME'];
            $field = [];

            $type = $field['type'] = $row['DATA_TYPE'];

            switch ($type) {
                case 'char':
                case 'varchar':
                    if (!is_null($row['CHARACTER_MAXIMUM_LENGTH'])) {
                        $field['length'] = (int) $row['CHARACTER_MAXIMUM_LENGTH'];
                    }
                    break;
                case 'decimal':
                    // Other int types may report these values, but they're irrelevant.
                    // Just ignore them!
                    if (!is_null($row['NUMERIC_PRECISION'])) {
                        $field['precision'] = (int) $row['NUMERIC_PRECISION'];
                    }
                    if (!is_null($row['NUMERIC_SCALE'])) {
                        $field['scale'] = (int) $row['NUMERIC_SCALE'];
                    }
                    break;
                case 'enum':
                    $enum = preg_replace("/^enum\('(.+)'\)$/", '\1', $row['COLUMN_TYPE']);
                    $field['enum'] = explode("','", $enum);
                    break;
            }


            if ($row['IS_NULLABLE'] == 'NO') {
                $field['not null'] = true;
            }
            $col_default = $row['COLUMN_DEFAULT'];
            if (!is_null($col_default) && $col_default !== 'NULL') {
                if ($this->isNumericType($field)) {
                    $field['default'] = (int) $col_default;
                } elseif ($col_default === 'CURRENT_TIMESTAMP'
                          || $col_default === 'current_timestamp()') {
                    // A hack for "datetime" fields
                    // Skip "timestamp" as they get a CURRENT_TIMESTAMP default implicitly
                    if ($type !== 'timestamp') {
                        $field['default'] = 'CURRENT_TIMESTAMP';
                    }
                } else {
                    $match = "/^'(.*)'$/";
                    if (preg_match($match, $col_default)) {
                        $field['default'] = preg_replace($match, '\1', $col_default);
                    } else {
                        $field['default'] = $col_default;
                    }
                }
            }
            if ($row['COLUMN_KEY'] !== null) {
                // We'll need to look up key info...
                $hasKeys = true;
            }
            if ($row['COLUMN_COMMENT'] !== null && $row['COLUMN_COMMENT'] != '') {
                $field['description'] = $row['COLUMN_COMMENT'];
            }

            $extra = $row['EXTRA'];
            if ($extra) {
                if (preg_match('/(^|\s)auto_increment(\s|$)/i', $extra)) {
                    $field['auto_increment'] = true;
                }
            }

            if (!empty($row['COLLATION_NAME'])) {
                $field['collate'] = $row['COLLATION_NAME'];
            }

            $def['fields'][$name] = $field;
        }

        if ($hasKeys) {
            $key_info = $this->fetchKeyInfo($table);

            foreach ($key_info as $row) {
                $key_name = $row['key_name'];
                $cols = $row['cols'];

                switch ($row['key_type']) {
                    case 'PRIMARY':
                        $def['primary key'] = $cols;
                        break;
                    case 'UNIQUE':
                        $def['unique keys'][$key_name] = $cols;
                        break;
                    case 'FULLTEXT':
                        $def['fulltext indexes'][$key_name] = $cols;
                        break;
                    default:
                        $def['indexes'][$key_name] = $cols;
                }
            }
        }

        $foreign_key_info = $this->fetchForeignKeyInfo($table);

        foreach ($foreign_key_info as $row) {
            $key_name = $row['key_name'];
            $cols = $row['cols'];
            $ref_table = $row['ref_table'];

            $def['foreign keys'][$key_name] = [$ref_table, $cols];
        }
        return $def;
    }

    /**
     * Pull the given table properties from INFORMATION_SCHEMA.
     * Most of the good stuff is MySQL extensions.
     *
     * @param $table
     * @param $props
     * @return array
     * @throws PEAR_Exception
     * @throws SchemaTableMissingException
     */
    public function getTableProperties($table, $props)
    {
        $data = $this->fetchMetaInfo($table, 'TABLES');
        if ($data) {
            return $data[0];
        } else {
            throw new SchemaTableMissingException("No such table: $table");
        }
    }

    /**
     * Pull some INFORMATION.SCHEMA data for the given table.
     *
     * @param string $table
     * @param $infoTable
     * @param null $orderBy
     * @return array of arrays
     * @throws PEAR_Exception
     */
    public function fetchMetaInfo($table, $infoTable, $orderBy = null)
    {
        $schema = $this->conn->getDatabase();
        $info = $this->fetchQueryData(sprintf(
            <<<'END'
            SELECT * FROM INFORMATION_SCHEMA.%1$s
              WHERE TABLE_SCHEMA = '%2$s' AND TABLE_NAME = '%3$s'%4$s;
            END,
            $this->quoteIdentifier($infoTable),
            $schema,
            $table,
            ($orderBy ? " ORDER BY {$orderBy}" : '')
        ));

        return array_map(function (array $cols): array {
            return array_change_key_case($cols, CASE_UPPER);
        }, $info);
    }

    /**
     * Pull index and keys information for the given table.
     *
     * @param string $table
     * @return array of arrays
     * @throws PEAR_Exception
     */
    private function fetchKeyInfo(string $table): array
    {
        $schema = $this->conn->getDatabase();
        $data = $this->fetchQueryData(
            <<<EOT
            SELECT INDEX_NAME AS `key_name`,
                CASE
                  WHEN INDEX_NAME = 'PRIMARY' THEN 'PRIMARY'
                  WHEN NON_UNIQUE IS NOT TRUE THEN 'UNIQUE'
                  ELSE INDEX_TYPE
                END AS `key_type`,
                COLUMN_NAME AS `col`,
                SUB_PART AS `col_length`
              FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = '{$schema}' AND TABLE_NAME = '{$table}'
              ORDER BY `key_name`, `key_type`, SEQ_IN_INDEX;
            EOT
        );

        $rows = [];
        foreach ($data as $row) {
            $name = $row['key_name'];

            if (!is_null($row['col_length'])) {
                $row['col'] = [$row['col'], (int) $row['col_length']];
            }
            unset($row['col_length']);

            if (!array_key_exists($name, $rows)) {
                $row['cols'] = [$row['col']];

                unset($row['col']);
                $rows[$name] = $row;
            } else {
                $rows[$name]['cols'][] = $row['col'];
            }
        }

        return array_values($rows);
    }

    /**
     * Pull foreign key information for the given table.
     *
     * @param string $table
     * @return array array of arrays
     * @throws PEAR_Exception
     */
    private function fetchForeignKeyInfo(string $table): array
    {
        $schema = $this->conn->getDatabase();
        $data = $this->fetchQueryData(
            <<<END
            SELECT CONSTRAINT_NAME AS `key_name`,
                COLUMN_NAME AS `col`,
                REFERENCED_TABLE_NAME AS `ref_table`,
                REFERENCED_COLUMN_NAME AS `ref_col`
              FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = '{$schema}'
              AND TABLE_NAME = '{$table}'
              AND REFERENCED_TABLE_SCHEMA = '{$schema}'
              ORDER BY `key_name`, ORDINAL_POSITION;
            END
        );

        $rows = [];
        foreach ($data as $row) {
            $name = $row['key_name'];

            if (!array_key_exists($name, $rows)) {
                $row['cols'] = [$row['col'] => $row['ref_col']];

                unset($row['col']);
                unset($row['ref_col']);
                $rows[$name] = $row;
            } else {
                $rows[$name]['cols'][$row['col']] = $row['ref_col'];
            }
        }

        return array_values($rows);
    }

    /**
     * Append an SQL statement with an index definition for a full-text search
     * index over one or more columns on a table.
     *
     * @param array $statements
     * @param string $table
     * @param string $name
     * @param array $def
     */
    public function appendCreateFulltextIndex(array &$statements, $table, $name, array $def)
    {
        $statements[] = "CREATE FULLTEXT INDEX $name ON $table " . $this->buildIndexList($def);
    }

    /**
     * Append an SQL statement with an index definition for an advisory
     * index over one or more columns on a table.
     *
     * @param array $statements
     * @param string $table
     * @param string $name
     * @param array $def
     */
    public function appendCreateIndex(array &$statements, $table, $name, array $def)
    {
        $statements[] = "ALTER TABLE {$this->quoteIdentifier($table)} "
                      . "ADD INDEX {$name} {$this->buildIndexList($def)}";
    }

    /**
     * Close out a 'create table' SQL statement.
     *
     * @param string $name
     * @param array $def
     *
     * @return string
     */
    public function endCreateTable($name, array $def)
    {
        $engine = self::storageEngine($def);
        $charset = self::charset();
        return ") ENGINE '{$engine}' "
             . "DEFAULT CHARACTER SET '{$charset}' "
             . "DEFAULT COLLATE '{$charset}_bin'";
    }

    /**
     * Returns the character set of choice for MariaDB.
     * Overrides default standard "UTF8".
     *
     * @return string
     */
    public static function charset(): string
    {
        return 'utf8mb4';
    }

    /**
     * Returns the storage engine of choice for the supplied definition.
     *
     * @param array $def
     * @return string
     */
    protected static function storageEngine(array $def): string
    {
        return 'InnoDB';
    }

    /**
     * Append phrase(s) to an array of partial ALTER TABLE chunks in order
     * to alter the given column from its old state to a new one.
     *
     * @param array $phrase
     * @param string $columnName
     * @param array $old previous column definition as found in DB
     * @param array $cd current column definition
     */
    public function appendAlterModifyColumn(
        array &$phrase,
        string $columnName,
        array  $old,
        array  $cd
    ): void {
        $phrase[] = 'MODIFY COLUMN ' . $this->quoteIdentifier($columnName)
                  . ' ' . $this->columnSql($columnName, $cd);
    }

    /**
     * MySQL doesn't take 'DROP CONSTRAINT', need to treat primary keys as
     * if they were indexes here, but can use 'PRIMARY KEY' special name.
     *
     * @param array $phrase
     */
    public function appendAlterDropPrimary(array &$phrase, string $tableName)
    {
        $phrase[] = 'DROP PRIMARY KEY';
    }

    /**
     * MySQL doesn't take 'DROP CONSTRAINT', need to treat unique keys as
     * if they were indexes here.
     *
     * @param array $phrase
     * @param string $keyName MySQL
     */
    public function appendAlterDropUnique(array &$phrase, $keyName)
    {
        $phrase[] = 'DROP INDEX ' . $keyName;
    }

    public function appendAlterDropForeign(array &$phrase, $keyName)
    {
        $phrase[] = 'DROP FOREIGN KEY ' . $keyName;
    }

    /**
     * Throw some table metadata onto the ALTER TABLE if we have a mismatch
     * in expected type, collation.
     * @param array $phrase
     * @param $tableName
     * @param array $def
     * @throws Exception
     */
    public function appendAlterExtras(array &$phrase, $tableName, array $def)
    {
        // Check for table properties: make sure we are using sane
        // storage engine, character set and collation.
        $oldProps = $this->getTableProperties($tableName, ['ENGINE', 'TABLE_COLLATION']);
        $engine = self::storageEngine($def);
        $charset = self::charset();
        if (mb_strtolower($oldProps['ENGINE']) !== mb_strtolower($engine)) {
            $phrase[] = "ENGINE '{$engine}'";
        }
        if (strtolower($oldProps['TABLE_COLLATION']) !== "{$charset}_bin") {
            $phrase[] = "CONVERT TO CHARACTER SET '{$charset}' COLLATE '{$charset}_bin'";
            $phrase[] = "DEFAULT CHARACTER SET '{$charset}'";
            $phrase[] = "DEFAULT COLLATE '{$charset}_bin'";
        }
    }

    /**
     * Append an SQL statement to drop an index from a table.
     * Note that in MariaDB index names are relation-specific.
     *
     * @param array $statements
     * @param string $table
     * @param string $name
     */
    public function appendDropIndex(array &$statements, $table, $name)
    {
        $statements[] = "ALTER TABLE {$this->quoteIdentifier($table)} "
                      . "DROP INDEX {$name}";
    }

    private function isNumericType(array $cd): bool
    {
        $ints = array_map(
            function ($s) {
                return $s . 'int';
            },
            ['tiny', 'small', 'medium', 'big']
        );
        $ints = array_merge($ints, ['int', 'numeric', 'serial']);
        return in_array(strtolower($cd['type']), $ints);
    }

    /**
     * Return the proper SQL for creating or
     * altering a column.
     *
     * Appropriate for use in CREATE TABLE or
     * ALTER TABLE statements.
     *
     * @param string $name column name to create
     * @param array $cd column to create
     *
     * @return string correct SQL for that column
     */
    public function columnSql(string $name, array $cd)
    {
        $line = [];
        $line[] = parent::columnSql($name, $cd);

        // This'll have been added from our transform of "serial" type
        if (!empty($cd['auto_increment'])) {
            $line[] = 'AUTO_INCREMENT';
        }

        if (!empty($cd['description'])) {
            $line[] = 'COMMENT';
            $line[] = $this->quoteValue($cd['description']);
        }

        return implode(' ', $line);
    }

    public function mapType($column)
    {
        $map = [
            'integer' => 'int',
            'numeric' => 'decimal',
            'blob'    => 'longblob',
        ];

        $type = $column['type'];
        if (array_key_exists($type, $map)) {
            $type = $map[$type];
        }

        $size = $column['size'] ?? null;
        switch ($type) {
            case 'int':
                if (in_array($size, ['tiny', 'small', 'medium', 'big'])) {
                    $type = $size . $type;
                }
                break;
            case 'float':
                if ($size === 'big') {
                    $type = 'double';
                }
                break;
            case 'text':
                if (in_array($size, ['tiny', 'medium', 'long'])) {
                    $type = $size . $type;
                }
                break;
        }

        return $type;
    }

    /**
     * Collation in MariaDB format from our format
     *
     * @param string $collate
     * @return string
     */
    protected function collationToMySQL(string $collate): string
    {
        if (!in_array($collate, [
            'utf8_bin',
            'utf8_general_cs',
            'utf8_general_ci',
        ])) {
            common_log(
                LOG_ERR,
                'Collation not supported: "' . $collate . '"'
            );
            $collate = 'utf8_bin';
        }

        if (substr($collate, 0, 13) === 'utf8_general_') {
            $collate = 'utf8mb4_unicode_' . substr($collate, 13);
        } elseif (substr($collate, 0, 5) === 'utf8_') {
            $collate = 'utf8mb4_' . substr($collate, 5);
        }
        return $collate;
    }

    public function typeAndSize(string $name, array $column)
    {
        if ($column['type'] === 'enum') {
            $vals = [];
            foreach ($column['enum'] as &$val) {
                $vals[] = "'{$val}'";
            }
            return 'ENUM(' . implode(',', $vals) . ')';
        } elseif ($this->isStringType($column)) {
            $col = parent::typeAndSize($name, $column);
            if (!empty($column['collate'])) {
                $col .= " COLLATE '{$column['collate']}'";
            }
            return $col;
        } else {
            return parent::typeAndSize($name, $column);
        }
    }

    /**
     * Filter the given table definition array to match features available
     * in this database.
     *
     * This lets us strip out unsupported things like comments, foreign keys,
     * or type variants that we wouldn't get back from getTableDef().
     *
     * @param string $tableName
     * @param array $tableDef
     * @return array
     */
    public function filterDef(string $tableName, array $tableDef)
    {
        $tableDef = parent::filterDef($tableName, $tableDef);

        foreach ($tableDef['fields'] as $name => &$col) {
            switch ($col['type']) {
                case 'serial':
                    $col['type'] = 'int';
                    $col['auto_increment'] = true;
                    break;
                case 'bool':
                    $col['type'] = 'int';
                    $col['size'] = 'tiny';
                    $col['default'] = (int) $col['default'];
                    break;
            }

            if (!empty($col['collate'])) {
                $col['collate'] = $this->collationToMySQL($col['collate']);
            }

            $col['type'] = $this->mapType($col);
            unset($col['size']);
        }
        return $tableDef;
    }
}
