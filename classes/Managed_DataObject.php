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
 * Wrapper for Memcached_DataObject which knows its own schema definition.
 * Builds its own damn settings from a schema definition.
 *
 * @package   GNUsocial
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

abstract class Managed_DataObject extends Memcached_DataObject
{
    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Get an instance by key
     *
     * @param string $k Key to use to lookup (usually 'id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return get_called_class() object if found, or null for no hits
     *
     */
    public static function getKV($k, $v = null)
    {
        return parent::getClassKV(get_called_class(), $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return get_called_class() object if found, or null for no hits
     *
     */
    public static function pkeyGet(array $kv)
    {
        return parent::pkeyGetClass(get_called_class(), $kv);
    }

    public static function pkeyCols()
    {
        return parent::pkeyColsClass(get_called_class());
    }

    /**
     * Get multiple items from the database by key
     *
     * @param  string $keyCol    name of column for key
     * @param  array  $keyVals   key values to fetch
     * @param  bool   $skipNulls return only non-null results
     * @param  bool   $preserve  return the same tuples as input
     * @return object An object with tuples to be fetched, in order
     */
    public static function multiGet(
        string $keyCol,
        array  $keyVals,
        bool   $skipNulls = true,
        bool   $preserve  = false
    ): object {
        return parent::multiGetClass(
            get_called_class(),
            $keyCol,
            $keyVals,
            $skipNulls,
            $preserve
        );
    }

    /**
     * Get multiple items from the database by key
     *
     * @param string  $keyCol    name of column for key
     * @param array   $keyVals   key values to fetch
     * @param array   $otherCols Other columns to hold fixed
     *
     * @return array Array mapping $keyVals to objects, or null if not found
     */
    public static function pivotGet($keyCol, array $keyVals, array $otherCols = [])
    {
        return parent::pivotGetClass(get_called_class(), $keyCol, $keyVals, $otherCols);
    }

    /**
     * Get a multi-instance object
     *
     * This is a utility method to get multiple instances with a given set of
     * values for a specific column.
     *
     * @param string $keyCol  key column name
     * @param array  $keyVals array of key values
     *
     * @return get_called_class() object with multiple instances if found,
     *         Exception is thrown when no entries are found.
     *
     */
    public static function listFind($keyCol, array $keyVals)
    {
        return parent::listFindClass(get_called_class(), $keyCol, $keyVals);
    }

    /**
     * Get a multi-instance object separated into an array
     *
     * This is a utility method to get multiple instances with a given set of
     * values for a specific key column. Usually used for the primary key when
     * multiple values are desired. Result is an array.
     *
     * @param string $keyCol  key column name
     * @param array  $keyVals array of key values
     *
     * @return array with an get_called_class() object for each $keyVals entry
     *
     */
    public static function listGet($keyCol, array $keyVals)
    {
        return parent::listGetClass(get_called_class(), $keyCol, $keyVals);
    }

    /**
     * get/set an associative array of table columns
     *
     * @access public
     * @return array (associative)
     */
    public function table()
    {
        $table = static::schemaDef();
        return array_map(array($this, 'columnBitmap'), $table['fields']);
    }

    /**
     * get/set an  array of table primary keys
     *
     * Key info is pulled from the table definition array.
     *
     * @access private
     * @return array
     */
    public function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * Get a sequence key
     *
     * Returns the first serial column defined in the table, if any.
     *
     * @access private
     * @return array (column,use_native,sequence_name)
     */

    public function sequenceKey()
    {
        $table = static::schemaDef();
        foreach ($table['fields'] as $name => $column) {
            if ($column['type'] == 'serial') {
                // We have a serial/autoincrement column.
                // Declare it to be a native sequence!
                return array($name, true, false);
            }
        }

        // No sequence key on this table.
        return array(false, false, false);
    }

    /**
     * Return key definitions for DB_DataObject and Memcache_DataObject.
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    public function keyTypes()
    {
        $table = static::schemaDef();
        $keys = array();

        if (!empty($table['unique keys'])) {
            foreach ($table['unique keys'] as $idx => $fields) {
                foreach ($fields as $name) {
                    $keys[$name] = 'U';
                }
            }
        }

        if (!empty($table['primary key'])) {
            foreach ($table['primary key'] as $name) {
                $keys[$name] = 'K';
            }
        }
        return $keys;
    }

    /**
     * Build the appropriate DB_DataObject bitfield map for this field.
     *
     * @param array $column
     * @return int
     */
    public function columnBitmap($column)
    {
        $type = $column['type'];

        // For quoting style...
        $intTypes = [
            'int',
            'float',
            'serial',
            'numeric'
        ];
        if (in_array($type, $intTypes)) {
            $style = DB_DATAOBJECT_INT;
        } else {
            $style = DB_DATAOBJECT_STR;
        }

        // Data type formatting style...
        $formatStyles = [
            'blob'      => DB_DATAOBJECT_BLOB,
            'text'      => DB_DATAOBJECT_TXT,
            'bool'      => DB_DATAOBJECT_BOOL,
            'date'      => DB_DATAOBJECT_DATE,
            'time'      => DB_DATAOBJECT_TIME,
            'datetime'  => DB_DATAOBJECT_DATE | DB_DATAOBJECT_TIME,
        ];

        if (isset($formatStyles[$type])) {
            $style |= $formatStyles[$type];
        }

        // Nullable?
        if (!empty($column['not null'])) {
            $style |= DB_DATAOBJECT_NOTNULL;
        }

        return $style;
    }

    public function links()
    {
        $links = array();

        $table = static::schemaDef();

        foreach ($table['foreign keys'] as $keyname => $keydef) {
            if (count($keydef) == 2 && is_string($keydef[0]) && is_array($keydef[1]) && count($keydef[1]) == 1) {
                if (isset($keydef[1][0])) {
                    $links[$keydef[1][0]] = $keydef[0].':'.$keydef[1][1];
                }
            }
        }
        return $links;
    }

    /**
     * Return a list of all primary/unique keys / vals that will be used for
     * caching. This will understand compound unique keys, which
     * Memcached_DataObject doesn't have enough info to handle properly.
     *
     * @return array of strings
     * @throws MethodNotImplementedException
     * @throws ServerException
     */
    public function _allCacheKeys()
    {
        $table = static::schemaDef();
        $ckeys = array();

        if (!empty($table['unique keys'])) {
            $keyNames = $table['unique keys'];
            foreach ($keyNames as $idx => $fields) {
                $val = array();
                foreach ($fields as $name) {
                    $val[$name] = self::valueString($this->$name);
                }
                $ckeys[] = self::multicacheKey($this->tableName(), $val);
            }
        }

        if (!empty($table['primary key'])) {
            $fields = $table['primary key'];
            $val = array();
            foreach ($fields as $name) {
                $val[$name] = self::valueString($this->$name);
            }
            $ckeys[] = self::multicacheKey($this->tableName(), $val);
        }
        return $ckeys;
    }

    /**
     * Returns an object by looking at the primary key column(s).
     *
     * Will require all primary key columns to be defined in an associative array
     * and ignore any keys which are not part of the primary key.
     *
     * Will NOT accept NULL values as part of primary key.
     *
     * @param   array   $vals       Must match all primary key columns for the dataobject.
     *
     * @return  Managed_DataObject  of the get_called_class() type
     * @throws  NoResultException   if no object with that primary key
     */
    public static function getByPK(array $vals)
    {
        $classname = get_called_class();

        $pkey = static::pkeyCols();
        if (is_null($pkey)) {
            throw new ServerException("Failed to get primary key columns for class '{$classname}'");
        }

        $object = new $classname();
        foreach ($pkey as $col) {
            if (!array_key_exists($col, $vals)) {
                throw new ServerException("Missing primary key column '{$col}' for ".get_called_class()." among provided keys: ".implode(',', array_keys($vals)));
            } elseif (is_null($vals[$col])) {
                throw new ServerException("NULL values not allowed in getByPK for column '{$col}'");
            }
            $object->$col = $vals[$col];
        }
        if (!$object->find(true)) {
            throw new NoResultException($object);
        }
        return $object;
    }

    /**
     * Returns an object by looking at given unique key columns.
     *
     * Will NOT accept NULL values for a unique key column. Ignores non-key values.
     *
     * @param   array   $vals       All array keys which are set must be non-null.
     *
     * @return  Managed_DataObject  of the get_called_class() type
     * @throws  NoResultException   if no object with that primary key
     */
    public static function getByKeys(array $vals)
    {
        $classname = get_called_class();

        $object = new $classname();

        $keys = $object->keys();
        if (is_null($keys)) {
            throw new ServerException("Failed to get key columns for class '{$classname}'");
        }

        foreach ($keys as $col) {
            if (!array_key_exists($col, $vals)) {
                continue;
            } elseif (is_null($vals[$col])) {
                throw new ServerException("NULL values not allowed in getByKeys for column '{$col}'");
            }
            $object->$col = $vals[$col];
        }
        if (!$object->find(true)) {
            throw new NoResultException($object);
        }
        return $object;
    }

    public static function getByID($id)
    {
        if (!property_exists(get_called_class(), 'id')) {
            throw new ServerException('Trying to get undefined property of dataobject class.');
        }
        if (empty($id)) {
            throw new EmptyPkeyValueException(get_called_class(), 'id');
        }
        // getByPK throws exception if id is null
        // or if the class does not have a single 'id' column as primary key
        return static::getByPK(array('id' => $id));
    }

    public static function getByUri($uri)
    {
        if (!property_exists(get_called_class(), 'uri')) {
            throw new ServerException('Trying to get undefined property of dataobject class.');
        }
        if (empty($uri)) {
            throw new EmptyPkeyValueException(get_called_class(), 'uri');
        }

        $class = get_called_class();
        $obj = new $class();
        $obj->uri = $uri;
        if (!$obj->find(true)) {
            throw new NoResultException($obj);
        }
        return $obj;
    }

    /**
     * Returns an ID, checked that it is set and reasonably valid
     *
     * If this dataobject uses a special id field (not 'id'), just
     * implement your ID getting method in the child class.
     *
     * @return int ID of dataobject
     * @throws Exception (when ID is not available or not set yet)
     */
    public function getID()
    {
        // FIXME: Make these exceptions more specific (their own classes)
        if (!isset($this->id)) {
            throw new Exception('No ID set.');
        } elseif (empty($this->id)) {
            throw new Exception('Empty ID for object! (not inserted yet?).');
        }

        return intval($this->id);
    }

    /**
     * Check whether the column is NULL in SQL
     *
     * @param string $key column property name
     *
     * @return bool
     */
    public function isNull(string $key): bool
    {
        if (array_key_exists($key, get_object_vars($this))
            && is_null($this->$key)) {
            // If there was no fetch, this is a false positive.
            return true;
        } elseif (is_object($this->$key)
                  && $this->$key instanceof DB_DataObject_Cast
                  && $this->$key->type === 'sql') {
            // This is cast to raw SQL, let's see if it's NULL.
            return (strcasecmp($this->$key->value, 'NULL') == 0);
        } elseif (DB_DataObject::_is_null($this, $key)) {
            // DataObject's NULL magic should be disabled,
            // this is just for completeness.
            return true;
        }
        return false;
    }

    /**
     * WARNING: Only use this on Profile and Notice. We should probably do
     * this with traits/"implements" or whatever, but that's over the top
     * right now, I'm just throwing this in here to avoid code duplication
     * in Profile and Notice classes.
     */
    public function getAliases()
    {
        return array_keys($this->getAliasesWithIDs());
    }

    public function getAliasesWithIDs()
    {
        $aliases = array();
        $aliases[$this->getUri()] = $this->getID();

        try {
            $aliases[$this->getUrl()] = $this->getID();
        } catch (InvalidUrlException $e) {
            // getUrl failed because no valid URL could be returned, just ignore it
        }

        if (common_config('fix', 'fancyurls')) {
            /**
             * Here we add some hacky hotfixes for remote lookups that have been taught the
             * (at least now) wrong URI but it's still obviously the same user. Such as:
             * - https://site.example/user/1 even if the client requests https://site.example/index.php/user/1
             * - https://site.example/user/1 even if the client requests https://site.example//index.php/user/1
             * - https://site.example/index.php/user/1 even if the client requests https://site.example/user/1
             * - https://site.example/index.php/user/1 even if the client requests https://site.example///index.php/user/1
             */
            foreach ($aliases as $alias=>$id) {
                try {
                    // get a "fancy url" version of the alias, even without index.php/
                    $alt_url = common_fake_local_fancy_url($alias);
                    // store this as well so remote sites can be sure we really are the same profile
                    $aliases[$alt_url] = $id;
                } catch (Exception $e) {
                    // Apparently we couldn't rewrite that, the $alias was as the function wanted it to be
                }

                try {
                    // get a non-"fancy url" version of the alias, i.e. add index.php/
                    $alt_url = common_fake_local_nonfancy_url($alias);
                    // store this as well so remote sites can be sure we really are the same profile
                    $aliases[$alt_url] = $id;
                } catch (Exception $e) {
                    // Apparently we couldn't rewrite that, the $alias was as the function wanted it to be
                }
            }
        }
        return $aliases;
    }

    /**
     * Set the attribute defined as "timestamp" to CURRENT_TIMESTAMP.
     * This is hooked in update() and updateWithKeys() to update "modified".
     *
     * @access private
     * @return void
     */
    private function updateAutoTimestamps(): void
    {
        $table = static::schemaDef();
        foreach ($table['fields'] as $name => $col) {
            if ($col['type'] === 'timestamp'
                && !array_key_exists('default', $col)
                && !isset($this->$name)) {
                $this->$name = common_sql_now();
            }
        }
    }

    /**
     * update() won't write key columns, so we have to do it ourselves.
     * This also automatically calls "update" _before_ it sets the keys.
     * FIXME: This only works with single-column primary keys so far! Beware!
     *
     * @param Managed_DataObject $orig Must be "instanceof" $this
     * @param string $pid Primary ID column (no escaping is done on column name!)
     * @return bool|void
     * @throws MethodNotImplementedException
     * @throws ServerException
     */
    public function updateWithKeys(Managed_DataObject $orig, ?string $pid = null)
    {
        if (!$orig instanceof $this) {
            throw new ServerException('Tried updating a DataObject with a different class than itself.');
        }

        if ($this->N <1) {
            throw new ServerException('DataObject must be the result of a query (N>=1) before updateWithKeys()');
        }

        $this->onUpdateKeys($orig);

        // do it in a transaction
        $this->query('START TRANSACTION');

        // ON UPDATE CURRENT_TIMESTAMP behaviour
        // @fixme Should the value be reverted back if transaction failed?
        $this->updateAutoTimestamps();

        $parts = [];
        foreach ($this->keys() as $k) {
            $v = $this->table()[$k];
            if ($this->$k !== $orig->$k) {
                if (is_object($this->$k) && $this->$k instanceof DB_DataObject_Cast) {
                    $value = $this->$k->toString($v, $this->getDatabaseConnection());
                } elseif (DB_DataObject::_is_null($this, $k)) {
                    $value = 'NULL';
                } elseif ($v & DB_DATAOBJECT_STR) { // if a string
                    $value = $this->_quote((string) $this->$k);
                } else {
                    $value = (int) $this->$k;
                }
                $parts[] = "{$k} = {$value}";
            }
        }
        if (count($parts) == 0) {
            // No changes to keys, it's safe to run ->update(...)
            if ($this->update($orig) === false) {
                common_log_db_error($this, 'UPDATE', __FILE__);
                // rollback as something bad occurred
                $this->query('ROLLBACK');
                throw new ServerException("Could not UPDATE non-keys for {$this->tableName()}");
            }
            $orig->decache();
            $this->encache();

            // commit our db transaction since we won't reach the COMMIT below
            $this->query('COMMIT');
            // @FIXME return true only if something changed (otherwise 0)
            return true;
        }

        if ($pid === null) {
            $schema = static::schemaDef();
            $pid = $schema['primary key'];
            unset($schema);
        }
        $pidWhere = [];
        foreach ((array) $pid as $pidCol) {
            $pidWhere[] = sprintf('%1$s = %2$s', $pidCol, $this->_quote($orig->$pidCol));
        }
        if (empty($pidWhere)) {
            throw new ServerException('No primary ID column(s) set for updateWithKeys');
        }

        $qry = sprintf(
            'UPDATE %1$s SET %2$s WHERE %3$s',
            $this->escapedTableName(),
            implode(', ', $parts),
            implode(' AND ', $pidWhere)
        );

        $result = $this->query($qry);
        if ($result === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // rollback as something bad occurred
            $this->query('ROLLBACK');
            throw new ServerException("Could not UPDATE key fields for {$this->tableName()}");
        }

        // Update non-keys too, if the previous endeavour worked.
        // The ->update call uses "$this" values for keys, that's why we can't do this until
        // the keys are updated (because they might differ from $orig and update the wrong entries).
        if ($this->update($orig) === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // rollback as something bad occurred
            $this->query('ROLLBACK');
            throw new ServerException("Could not UPDATE non-keys for {$this->tableName()}");
        }
        $orig->decache();
        $this->encache();

        // commit our db transaction
        $this->query('COMMIT');
        // @FIXME return true only if something changed (otherwise 0)
        return $result;
    }

    public static function beforeSchemaUpdate()
    {
        // NOOP
    }

    public static function newUri(Profile $actor, Managed_DataObject $object, $created = null)
    {
        if (is_null($created)) {
            $created = common_sql_now();
        }
        return TagURI::mint(
            strtolower(get_called_class()) . ':%d:%s:%d:%s',
            $actor->getID(),
            ActivityUtils::resolveUri($object->getObjectType(), true),
            $object->getID(),
            common_date_iso8601($created)
        );
    }

    protected function onInsert()
    {
        // NOOP by default
    }

    protected function onUpdate($dataObject=false)
    {
        // NOOP by default
    }

    protected function onUpdateKeys(Managed_DataObject $orig)
    {
        // NOOP by default
    }

    public function insert()
    {
        $this->onInsert();
        $result = parent::insert();

        // Make this object aware of the changed "modified" attribute.
        // Sets it approximately to the same value as DEFAULT CURRENT_TIMESTAMP
        // just did (@fixme).
        if ($result) {
            $this->updateAutoTimestamps();
        }
        return $result;
    }

    public function update($dataObject = false)
    {
        $this->onUpdate($dataObject);

        // ON UPDATE CURRENT_TIMESTAMP behaviour
        // @fixme Should the value be reverted back if transaction failed?
        $this->updateAutoTimestamps();

        return parent::update($dataObject);
    }
}
