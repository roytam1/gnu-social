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

defined('GNUSOCIAL') || die();

/**
 * Show notice attachments
 *
 * @category  Personal
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class AttachmentAction extends ManagedAction
{
    /**
     * Attachment File object to show
     */
    public $attachment = null;

    public $filehash = null;
    public $filepath = null;
    public $filesize = null;
    public $mimetype = null;
    public $filename = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return bool flag
     * @throws ClientException
     * @throws FileNotFoundException
     * @throws FileNotStoredLocallyException
     * @throws InvalidFilenameException
     * @throws ServerException
     */
    protected function prepare(array $args = [])
    {
        parent::prepare($args);

        try {
            if (!empty($id = $this->trimmed('attachment'))) {
                $this->attachment = File::getByID((int) $id);
            } elseif (!empty($this->filehash = $this->trimmed('filehash'))) {
                $file = File::getByHash($this->filehash);
                $file->fetch();
                $this->attachment = $file;
            }
        } catch (Exception $e) {
            // Not found
        }
        if (!$this->attachment instanceof File) {
            // TRANS: Client error displayed trying to get a non-existing attachment.
            $this->clientError(_m('No such attachment.'), 404);
        }

        $this->filesize = $this->attachment->size;
        $this->mimetype = $this->attachment->mimetype;
        $this->filename = $this->attachment->filename;

        if ($this->attachment->isLocal() || $this->attachment->isFetchedRemoteFile()) {
            $this->filesize = $this->attachment->getFileOrThumbnailSize();
            $this->mimetype = $this->attachment->getFileOrThumbnailMimetype();
            $this->filename = MediaFile::getDisplayName($this->attachment);
        }

        return true;
    }

    /**
     * Is this action read-only?
     *
     * @return bool true
     */
    public function isReadOnly($args): bool
    {
        return true;
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */
    public function title(): string
    {
        $a = new Attachment($this->attachment);
        return $a->title();
    }

    public function showPage(): void
    {
        parent::showPage();
    }

    /**
     * Fill the content area of the page
     *
     * Shows a single notice list item.
     *
     * @return void
     */
    public function showContent(): void
    {
        $ali = new Attachment($this->attachment, $this);
        $ali->show();
    }

    /**
     * Don't show page notice
     *
     * @return void
     */
    public function showPageNoticeBlock(): void
    {
    }

    /**
     * Show aside: this attachments appears in what notices
     *
     * @return void
     */
    public function showSections(): void
    {
        $ns = new AttachmentNoticeSection($this);
        $ns->show();
    }

    /**
     * Last-modified date for file
     *
     * @return int last-modified date as unix timestamp
     * @throws ServerException
     */
    public function lastModified(): ?int
    {
        if (common_config('site', 'use_x_sendfile')) {
            return null;
        }
        $path = $this->filepath;
        if (!empty($path)) {
            return filemtime($path);
        } else {
            return null;
        }
    }

    /**
     * etag header for file
     *
     * This returns the same data (inode, size, mtime) as Apache would,
     * but in decimal instead of hex.
     *
     * @return string etag http header
     * @throws ServerException
     */
    public function etag(): ?string
    {
        if (common_config('site', 'use_x_sendfile')) {
            return null;
        }

        $path = $this->filepath;

        $cache = Cache::instance();
        if ($cache) {
            if (empty($path)) {
                return null;
            }
            $key = Cache::key('attachments:etag:' . $path);
            $etag = $cache->get($key);
            if ($etag === false) {
                $etag = crc32(file_get_contents($path));
                $cache->set($key, $etag);
            }
            return $etag;
        }

        if (!empty($path)) {
            $stat = stat($path);
            return '"' . $stat['ino'] . '-' . $stat['size'] . '-' . $stat['mtime'] . '"';
        } else {
            return null;
        }
    }
}
