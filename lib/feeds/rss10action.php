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
 * Base class for RSS 1.0 feed actions
 *
 * @category  Mail
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @author    Earle Martin <earle@downlode.org>
 * @copyright 2008, 2009 StatusNet, Inc.
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

define('DEFAULT_RSS_LIMIT', 48);

class Rss10Action extends ManagedAction
{
    // This will contain the details of each feed item's author and be used to generate SIOC data.

    public $creators = [];
    public $limit = DEFAULT_RSS_LIMIT;
    public $notices = null;
    public $tags_already_output = [];

    public function isReadOnly($args)
    {
        return true;
    }

    protected function doPreparation()
    {
        $this->limit = $this->int('limit');

        if (empty($this->limit)) {
            $this->limit = DEFAULT_RSS_LIMIT;
        }

        if (common_config('site', 'private')) {
            if (!isset($_SERVER['PHP_AUTH_USER'])) {

                // This header makes basic auth go
                header('WWW-Authenticate: Basic realm="GNU social RSS"');

                // If the user hits cancel -- bam!
                $this->show_basic_auth_error();
                // the above calls 'exit'
            } else {
                $nickname = $_SERVER['PHP_AUTH_USER'];
                $password = $_SERVER['PHP_AUTH_PW'];

                if (!common_check_user($nickname, $password)) {
                    // basic authentication failed
                    list($proxy, $ip) = common_client_ip();

                    common_log(LOG_WARNING, "Failed RSS auth attempt, nickname = $nickname, proxy = $proxy, ip = $ip.");
                    $this->show_basic_auth_error();
                    // the above calls 'exit'
                }
            }
        }

        $this->doStreamPreparation();

        $this->notices = $this->getNotices($this->limit);
    }

    protected function doStreamPreparation()
    {
        // for example if we need to set $this->target or something
    }

    public function show_basic_auth_error()
    {
        http_response_code(401);
        header('Content-Type: application/xml; charset=utf-8');
        $this->startXML();
        $this->elementStart('hash');
        $this->element('error', null, 'Could not authenticate you.');
        $this->element('request', null, $_SERVER['REQUEST_URI']);
        $this->elementEnd('hash');
        $this->endXML();
        exit;
    }

    /**
     * Get the notices to output in this stream.
     *
     * @return array an array of Notice objects sorted in reverse chron
     */

    protected function getNotices()
    {
        return array();
    }

    /**
     * Get a description of the channel
     *
     * Returns an array with the following
     * @return array
     */

    public function getChannel()
    {
        return [
            'url'         => '',
            'title'       => '',
            'link'        => '',
            'description' => '',
        ];
    }

    public function getImage()
    {
        return null;
    }

    public function showPage()
    {
        $this->initRss();
        $this->showChannel();
        $this->showImage();

        if (count($this->notices)) {
            foreach ($this->notices as $n) {
                try {
                    $this->showItem($n);
                } catch (Exception $e) {
                    // log exceptions and continue
                    common_log(LOG_ERR, $e->getMessage());
                    continue;
                }
            }
        }

        $this->showCreators();
        $this->endRss();
    }

    public function showChannel()
    {
        $channel = $this->getChannel();
        $image = $this->getImage();

        $this->elementStart('channel', array('rdf:about' => $channel['url']));
        $this->element('title', null, $channel['title']);
        $this->element('link', null, $channel['link']);
        $this->element('description', null, $channel['description']);
        $this->element('cc:licence', [
            'rdf:resource' => common_config('license', 'url'),
        ]);

        if ($image) {
            $this->element('image', array('rdf:resource' => $image));
        }

        $this->elementStart('items');
        $this->elementStart('rdf:Seq');

        if (count($this->notices)) {
            foreach ($this->notices as $notice) {
                $this->element('rdf:li', array('rdf:resource' => $notice->uri));
            }
        }

        $this->elementEnd('rdf:Seq');
        $this->elementEnd('items');

        $this->elementEnd('channel');
    }

    public function showImage()
    {
        $image = $this->getImage();
        if ($image) {
            $channel = $this->getChannel();
            $this->elementStart('image', array('rdf:about' => $image));
            $this->element('title', null, $channel['title']);
            $this->element('link', null, $channel['link']);
            $this->element('url', null, $image);
            $this->elementEnd('image');
        }
    }

    public function showItem($notice)
    {
        $profile = $notice->getProfile();
        $nurl = common_local_url('shownotice', array('notice' => $notice->id));
        $creator_uri = common_profile_uri($profile);
        $this->elementStart('item', array('rdf:about' => $notice->uri,
                            'rdf:type' => 'http://rdfs.org/sioc/types#MicroblogPost'));
        $title = $profile->nickname . ': ' . common_xml_safe_str(trim($notice->content));
        $this->element('title', null, $title);
        $this->element('link', null, $nurl);
        $this->element('description', null, $profile->nickname."'s status on ".common_exact_date($notice->created));
        if ($notice->getRendered()) {
            $this->element('content:encoded', null, common_xml_safe_str($notice->getRendered()));
        }
        $this->element('dc:date', null, common_date_w3dtf($notice->created));
        $this->element('dc:creator', null, ($profile->fullname) ? $profile->fullname : $profile->nickname);
        $this->element('foaf:maker', array('rdf:resource' => $creator_uri));
        $this->element('sioc:has_creator', array('rdf:resource' => $creator_uri.'#acct'));
        try {
            $location = Notice_location::locFromStored($notice);
            if (isset($location->lat) && isset($location->lon)) {
                $location_uri = $location->getRdfURL();
                $attrs = array('geo:lat' => $location->lat,
                               'geo:long' => $location->lon);
                if (strlen($location_uri)) {
                    $attrs['rdf:resource'] = $location_uri;
                }
                $this->element('statusnet:origin', $attrs);
            }
        } catch (ServerException $e) {
            // No result, so no location data
        }
        $this->element('statusnet:postIcon', array('rdf:resource' => $profile->avatarUrl()));
        $this->element('cc:licence', array('rdf:resource' => common_config('license', 'url')));
        if ($notice->reply_to) {
            $replyurl = common_local_url('shownotice', array('notice' => $notice->reply_to));
            $this->element('sioc:reply_of', array('rdf:resource' => $replyurl));
        }
        if (!empty($notice->conversation)) {
            $conversationurl = common_local_url(
                'conversation',
                ['id' => $notice->conversation]
            );
            $this->element('sioc:has_discussion', [
                'rdf:resource' => $conversationurl,
            ]);
        }
        $attachments = $notice->attachments();
        if ($attachments) {
            foreach ($attachments as $attachment) {
                try {
                    $enclosure = $attachment->getEnclosure();
                    $attribs = array('rdf:resource' => $enclosure->url);
                    if ($enclosure->title) {
                        $attribs['dc:title'] = $enclosure->title;
                    }
                    if ($enclosure->modified) {
                        $attribs['dc:date'] = common_date_w3dtf($enclosure->modified);
                    }
                    if ($enclosure->size) {
                        $attribs['enc:length'] = $enclosure->size;
                    }
                    if ($enclosure->mimetype) {
                        $attribs['enc:type'] = $enclosure->mimetype;
                    }
                    $this->element('enc:enclosure', $attribs);
                } catch (ServerException $e) {
                    // There was not enough metadata available
                }
                $this->element('sioc:links_to', array('rdf:resource'=>$attachment->url));
            }
        }

        $tag = new Notice_tag();
        $tag->notice_id = $notice->id;
        if ($tag->find()) {
            $entry['tags']=array();
            while ($tag->fetch()) {
                $tagpage = common_local_url('tag', array('tag' => $tag->tag));

                if (in_array($tag, $this->tags_already_output)) {
                    $this->element('ctag:tagged', array('rdf:resource'=>$tagpage.'#concept'));
                    continue;
                }

                $tagrss  = common_local_url('tagrss', array('tag' => $tag->tag));
                $this->elementStart('ctag:tagged');
                $this->elementStart('ctag:Tag', array('rdf:about'=>$tagpage.'#concept', 'ctag:label'=>$tag->tag));
                $this->element('foaf:page', array('rdf:resource'=>$tagpage));
                $this->element('rdfs:seeAlso', array('rdf:resource'=>$tagrss));
                $this->elementEnd('ctag:Tag');
                $this->elementEnd('ctag:tagged');

                $this->tags_already_output[] = $tag->tag;
            }
        }
        $this->elementEnd('item');
        $this->creators[$creator_uri] = $profile;
    }

    public function showCreators()
    {
        foreach ($this->creators as $uri => $profile) {
            $id = $profile->id;
            $nickname = $profile->nickname;
            $this->elementStart('foaf:Agent', array('rdf:about' => $uri));
            $this->element('foaf:nick', null, $nickname);
            if ($profile->fullname) {
                $this->element('foaf:name', null, $profile->fullname);
            }
            $this->element('foaf:holdsAccount', array('rdf:resource' => $uri.'#acct'));
            $avatar = $profile->avatarUrl();
            $this->element('foaf:depiction', array('rdf:resource' => $avatar));
            $this->elementEnd('foaf:Agent');
        }
    }

    public function initRss()
    {
        $channel = $this->getChannel();
        header('Content-Type: application/rdf+xml');

        $this->startXml();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:dc' =>
                                              'http://purl.org/dc/elements/1.1/',
                                              'xmlns:cc' =>
                                              'http://creativecommons.org/ns#',
                                              'xmlns:content' =>
                                              'http://purl.org/rss/1.0/modules/content/',
                                              'xmlns:ctag' =>
                                              'http://commontag.org/ns#',
                                              'xmlns:foaf' =>
                                              'http://xmlns.com/foaf/0.1/',
                                              'xmlns:enc' =>
                                              'http://purl.oclc.org/net/rss_2.0/enc#',
                                              'xmlns:sioc' =>
                                              'http://rdfs.org/sioc/ns#',
                                              'xmlns:sioct' =>
                                              'http://rdfs.org/sioc/types#',
                                              'xmlns:rdfs' =>
                                              'http://www.w3.org/2000/01/rdf-schema#',
                                              'xmlns:geo' =>
                                              'http://www.w3.org/2003/01/geo/wgs84_pos#',
                                              'xmlns:statusnet' =>
                                              'http://status.net/ont/',
                                              'xmlns' => 'http://purl.org/rss/1.0/'));
        $this->elementStart('sioc:Site', array('rdf:about' => common_root_url()));
        $this->element('sioc:name', null, common_config('site', 'name'));
        $this->elementStart('sioc:space_of');
        $this->element('sioc:Container', array('rdf:about' =>
                                               $channel['url']));
        $this->elementEnd('sioc:space_of');
        $this->elementEnd('sioc:Site');
    }

    public function endRss()
    {
        $this->elementEnd('rdf:RDF');
    }

    /**
     * When was this page last modified?
     *
     */

    public function lastModified()
    {
        if (empty($this->notices)) {
            return null;
        }

        if (count($this->notices) == 0) {
            return null;
        }

        // FIXME: doesn't handle modified profiles, avatars, deleted notices

        return strtotime($this->notices[0]->created);
    }
}
