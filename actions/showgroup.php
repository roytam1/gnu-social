<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Group main page
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Group main page
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ShowgroupAction extends GroupAction
{
    /** page we're viewing. */
    var $page = null;
    var $notice = null;

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Title of the page
     *
     * @return string page title, with page number
     */
    function title()
    {
        $base = $this->group->getFancyName();

        if ($this->page == 1) {
            // TRANS: Page title for first group page. %s is a group name.
            return sprintf(_('%s group'), $base);
        } else {
            // TRANS: Page title for any but first group page.
            // TRANS: %1$s is a group name, $2$s is a page number.
            return sprintf(_('%1$s group, page %2$d'),
                           $base,
                           $this->page);
        }
    }

    public function getStream()
    {
        if ($this->scoped instanceof Profile && $this->scoped->isLocal() && $this->scoped->getUser()->streamModeOnly()) {
            $stream = new GroupNoticeStream($this->group, $this->scoped);
        } else {
            $stream = new ThreadingGroupNoticeStream($this->group, $this->scoped);
        }

        return $stream;
    }

    /**
     * Get a list of the feeds for this page
     *
     * @return void
     */
    function getFeeds()
    {
        $url =
          common_local_url('grouprss',
                           array('nickname' => $this->group->nickname));

        return array(new Feed(Feed::JSON,
                              common_local_url('ApiTimelineGroup',
                                               array('format' => 'as',
                                                     'id' => $this->group->id)),
                              // TRANS: Tooltip for feed link. %s is a group nickname.
                              sprintf(_('Notice feed for %s group (Activity Streams JSON)'),
                                      $this->group->nickname)),
                    new Feed(Feed::RSS1,
                              common_local_url('grouprss',
                                               array('nickname' => $this->group->nickname)),
                              // TRANS: Tooltip for feed link. %s is a group nickname.
                              sprintf(_('Notice feed for %s group (RSS 1.0)'),
                                      $this->group->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineGroup',
                                               array('format' => 'rss',
                                                     'id' => $this->group->id)),
                              // TRANS: Tooltip for feed link. %s is a group nickname.
                              sprintf(_('Notice feed for %s group (RSS 2.0)'),
                                      $this->group->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineGroup',
                                               array('format' => 'atom',
                                                     'id' => $this->group->id)),
                              // TRANS: Tooltip for feed link. %s is a group nickname.
                              sprintf(_('Notice feed for %s group (Atom)'),
                                      $this->group->nickname)),
                     new Feed(Feed::FOAF,
                              common_local_url('foafgroup',
                                               array('nickname' => $this->group->nickname)),
                              // TRANS: Tooltip for feed link. %s is a group nickname.
                              sprintf(_('FOAF for %s group'),
                                       $this->group->nickname)));
    }

    function showAnonymousMessage()
    {
        if (!(common_config('site','closed') || common_config('site','inviteonly'))) {
            // TRANS: Notice on group pages for anonymous users for StatusNet sites that accept new registrations.
            // TRANS: %s is the group name, %%%%site.name%%%% is the site name,
            // TRANS: %%%%action.register%%%% is the URL for registration, %%%%doc.help%%%% is a URL to help.
            // TRANS: This message contains Markdown links. Ensure they are formatted correctly: [Description](link).
            $m = sprintf(_('**%s** is a user group on %%%%site.name%%%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                'based on the Free Software [StatusNet](http://status.net/) tool. Its members share ' .
                'short messages about their life and interests. '.
                '[Join now](%%%%action.register%%%%) to become part of this group and many more! ([Read more](%%%%doc.help%%%%))'),
                     $this->group->getBestName());
        } else {
            // TRANS: Notice on group pages for anonymous users for StatusNet sites that accept no new registrations.
            // TRANS: %s is the group name, %%%%site.name%%%% is the site name,
            // TRANS: This message contains Markdown links. Ensure they are formatted correctly: [Description](link).
            $m = sprintf(_('**%s** is a user group on %%%%site.name%%%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                'based on the Free Software [StatusNet](http://status.net/) tool. Its members share ' .
                'short messages about their life and interests.'),
                     $this->group->getBestName());
        }
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($m));
        $this->elementEnd('div');
    }

    function extraHead()
    {
        if ($this->page != 1) {
            $this->element('link', array('rel' => 'canonical',
                                         'href' => $this->group->homeUrl()));
        }
    }
}
