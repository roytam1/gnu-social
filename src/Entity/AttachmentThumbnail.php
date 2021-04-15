<?php

// {{{ License
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
// }}}

namespace App\Entity;

use App\Core\Entity;
use DateTimeInterface;

/**
 * Entity for File thumbnails
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class AttachmentThumbnail extends Entity
{
    // {{{ Autocode
    private int $file_id;
    private int $width;
    private int $height;
    private DateTimeInterface $modified;

    public function setFileId(int $file_id): self
    {
        $this->file_id = $file_id;
        return $this;
    }

    public function getFileId(): int
    {
        return $this->file_id;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // }}} Autocode

    /**
     * Delete a file thumbnail. This table doesn't own all the files, only itself
     */
    public function delete(bool $flush = false, bool $delete_files_now = false, bool $cascading = false): string
    {
        // TODO Implement deleting file thumbnails
        return '';
    }

    public static function schemaDef(): array
    {
        return [
            'name'   => 'file_thumbnail',
            'fields' => [
                'file_
                id'  => ['type' => 'int', 'foreign key' => true, 'target' => 'File.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'thumbnail for what file'],
                'width'    => ['type' => 'int', 'not null' => true, 'description' => 'width of thumbnail'],
                'height'   => ['type' => 'int', 'not null' => true, 'description' => 'height of thumbnail'],
                'modified' => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['file_id', 'width', 'height'],
            'indexes'     => [
                'file_thumbnail_file_id_idx' => ['file_id'],
            ],
        ];
    }
}