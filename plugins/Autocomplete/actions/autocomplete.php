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
 * List profiles and groups for autocompletion
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2009-2013 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

/**
 * List users for autocompletion
 *
 * This is the form for adding a new g
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class AutocompleteAction extends Action
{
    protected $needLogin = true;

    private $result;

    /**
     * Last-modified date for page
     *
     * When was the content of this page last modified? Based on notice,
     * profile, avatar.
     *
     * @return int last-modified date as unix timestamp
     */
    public function lastModified()
    {
        $max = 0;
        foreach ($this->profiles as $profile) {
            $max = max($max, strtotime($profile->modified));
        }
        foreach ($this->groups as $group) {
            $max = max($max, strtotime($group->modified));
        }
        // but maybe this file has been modified after that and could
        // respond differently
        return max($max, filemtime(__FILE__));
    }

    /**
     * An entity tag for this page
     *
     * Shows the ETag for the page, based on the notice ID and timestamps
     * for the notice, profile, and avatar. It's weak, since we change
     * the date text "one hour ago", etc.
     *
     * @return string etag
     */
    public function etag()
    {
        return '"' . implode(':', array($this->arg('action'),
            common_user_cache_hash(),
            // the actual string can have funny characters in we don't want
            // showing up in the etag
            crc32($this->arg('term')),
            $this->arg('limit'),
            $this->lastModified())) . '"';
    }

    protected function prepare(array $args=array())
    {
        // If we die, show short error messages.
        GNUsocial::setApi(true);

        parent::prepare($args);

        $this->groups = [];
        $this->profiles = [];
        $term = $this->arg('term');
        $limit = (int) $this->arg('limit');
        // Prevent DOS attacks
        if ($limit > 200) {
            $limit = 200;
        }
        if (substr($term, 0, 1) === '@') {
            //profile search
            $term = substr($term, 1);
            $user_table = common_database_tablename('user');
            $profile = new Profile();
            $profile->_join .= sprintf(
                "\n" . <<<'END'
                INNER JOIN (
                  SELECT id FROM %s
                  UNION
                  SELECT subscribed AS id FROM subscription WHERE subscriber = %d
                ) AS t1 USING (id)
                END,
                $user_table,
                $this->scoped->id
            );
            $profile->whereAdd('nickname LIKE \'' . trim($profile->escape($term), '\'') . '%\'');
            $profile->orderBy('created, id');
            $profile->limit($limit);

            if ($profile->find()) {
                while ($profile->fetch()) {
                    $this->profiles[] = clone($profile);
                }
            }
        } elseif (substr($term, 0, 1) === '!') {
            //group search
            $term = substr($term, 1);
            $group = new User_group();
            //Can't post to groups we're not subscribed to...:
            $group->whereAdd(sprintf(
                'id IN (SELECT group_id FROM group_member WHERE profile_id = %d)',
                $this->scoped->id
            ));
            $group->whereAdd('nickname LIKE \'' . trim($group->escape($term), '\'') . '%\'');
            $group->orderBy('created, id');
            $group->limit($limit);

            if ($group->find()) {
                while ($group->fetch()) {
                    $this->groups[] = clone($group);
                }
            }
        }
        return true;
    }

    protected function handle()
    {
        parent::handle();

        $results = array();
        foreach ($this->profiles as $profile) {
            $avatarUrl = $profile->avatarUrl(AVATAR_MINI_SIZE);
            $acct = $profile->getAcctUri();
            $identifier = explode(':', $profile->getAcctUri(), 2)[1];
            $results[] = array(
                'value' => '@'.$identifier,
                'nickname' => $profile->getNickname(),
                'acct_uri' => $acct,
                'label'=> "${identifier} (".$profile->getFullname().")",
                'avatar' => $avatarUrl,
                'type' => 'user'
            );
        }
        foreach ($this->groups as $group) {
            $profile = $group->getProfile();
            // sigh.... encapsulate this upstream!
            if ($group->mini_logo) {
                $avatarUrl = $group->mini_logo;
            } else {
                $avatarUrl = User_group::defaultLogo(AVATAR_MINI_SIZE);
            }
            $acct = $profile->getAcctUri();
            $identifier = explode(':', $profile->getAcctUri(), 2)[1];
            $results[] = array(
                'value' => '!'.$identifier,
                'nickname' => $group->getNickname(),
                'acct_uri' => $acct,
                'label'=> "${identifier} (".$group->getFullname().")",
                'avatar' => $avatarUrl,
                'type' => 'group');
        }
        print json_encode($results);
    }

    /**
     * Is this action read-only?
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    public function isReadOnly($args)
    {
        return true;
    }
}
