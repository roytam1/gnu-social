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
 * ActivityPub implementation for GNU social
 *
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2018-2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 * @link      http://www.gnu.org/software/social/
 */

defined('GNUSOCIAL') || die();

/**
 * ActivityPub delete representation
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Activitypub_delete
{
    /**
     * Generates an ActivityStreams 2.0 representation of a Delete
     *
     * @param Notice $object
     * @return array pretty array to be used in a response
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    public static function delete_to_array($object): array
    {
        if ($object instanceof Notice) {
            return Activitypub_notice::notice_to_array($object);
        } else if ($object instanceof Profile) {
            $actor_uri = $object->getUri();
            return [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $actor_uri . '#delete',
                'type' => 'Delete',
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'actor' => $actor_uri,
                'object' => $object
            ];
        } else {
            throw new InvalidArgumentException();
        }
    }

    /**
     * Verifies if a given object is acceptable for a Delete Activity.
     *
     * @param array|string $object
     * @return bool
     * @throws Exception
     * @author Bruno Casteleiro <brunoccast@fc.up.pt>
     */
    public static function validate_object($object): bool
    {
        if (!is_array($object)) {
            if (!filter_var($object, FILTER_VALIDATE_URL)) {
                throw new Exception('Object is not a valid Object URI for Activity.');
            }
        } else {
            if (!isset($object['type'])) {
                throw new Exception('Object type was not specified for Delete Activity.');
            }
            if ($object['type'] !== "Tombstone" && $object['type'] !== "Person") {
                throw new Exception('Invalid Object type for Delete Activity.');
            }
            if (!isset($object['id'])) {
                throw new Exception('Object id was not specified for Delete Activity.');
            }
        }

        return true;
    }
}
