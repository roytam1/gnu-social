<?php
/**
 * XMPPHP: The PHP XMPP Library
 * Copyright (C) 2008  Nathanael C. Fritz
 * This file is part of SleekXMPP.
 *
 * XMPPHP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * XMPPHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with XMPPHP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category   xmpphp
 * @package    XMPPHP
 * @author     Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author     Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author     Michael Garvin <JID: gar@netflint.net>
 * @author     Alexander Birkner (https://github.com/BirknerAlex)
 * @author     zorn-v (https://github.com/zorn-v/xmpphp/)
 * @author     GNU social
 * @copyright  2008 Nathanael C. Fritz
 */

namespace XMPPHP;

/** XMPPHP_XMLStream */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'XMLStream.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Roster.php';


/**
 * XMPPHP XMPP
 *
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 * @version   $Id$
 */
class XMPP extends XMLStream
{
    /**
     * @var string
     */
    public $server;

    /**
     * @var string
     */
    public $user;
    /**
     * @var bool
     */
    public $track_presence = true;
    /**
     * @var object
     */
    public $roster;
    /**
     * @var string
     */
    protected $password;
    /**
     * @var string
     */
    protected $resource;
    /**
     * @var string
     */
    protected $fulljid;
    /**
     * @var string
     */
    protected $basejid;
    /**
     * @var bool
     */
    protected $authed = false;
    protected $session_started = false;
    /**
     * @var bool
     */
    protected $auto_subscribe = false;
    /**
     * @var bool
     */
    protected $use_encryption = true;

    /**
     * Constructor
     *
     * @param string $host
     * @param integer $port
     * @param string $user
     * @param string $password
     * @param string $resource
     * @param string $server
     * @param bool $print_log
     * @param string $log_level
     */
    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $resource,
        ?string $server = null,
        bool $print_log = false,
        ?string $log_level = null
    ) {
        parent::__construct($host, $port, $print_log, $log_level);

        $this->user = $user;
        $this->password = $password;
        $this->resource = $resource;
        if (!$server) {
            $server = $host;
        }
        $this->server = $server;
        $this->basejid = $this->user . '@' . $this->host;

        $this->roster = new Roster();
        $this->track_presence = true;

        $this->stream_start = '<stream:stream to="' . $server . '" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client" version="1.0">';
        $this->stream_end = '</stream:stream>';
        $this->default_ns = 'jabber:client';

        $this->addXPathHandler(
            '{http://etherx.jabber.org/streams}features',
            [$this, 'features_handler']
        );
        $this->addXPathHandler(
            '{urn:ietf:params:xml:ns:xmpp-sasl}success',
            [$this, 'sasl_success_handler']
        );
        $this->addXPathHandler(
            '{urn:ietf:params:xml:ns:xmpp-sasl}failure',
            [$this, 'sasl_failure_handler']
        );
        $this->addXPathHandler(
            '{urn:ietf:params:xml:ns:xmpp-tls}proceed',
            [$this, 'tls_proceed_handler']
        );
        $this->addXPathHandler(
            '{jabber:client}message',
            [$this, 'message_handler']
        );
        $this->addXPathHandler(
            '{jabber:client}presence',
            [$this, 'presence_handler']
        );
        $this->addXPathHandler(
            'iq/{jabber:iq:roster}query',
            [$this, 'roster_iq_handler']
        );
    }

    /**
     * Turn encryption on/ff
     *
     * @param bool $useEncryption (optional)
     */
    public function useEncryption(bool $useEncryption = true): void
    {
        $this->use_encryption = $useEncryption;
    }

    /**
     * Turn on auto-authorization of subscription requests.
     *
     * @param bool $autoSubscribe (optional)
     */
    public function autoSubscribe(bool $autoSubscribe = true): void
    {
        $this->auto_subscribe = $autoSubscribe;
    }

    /**
     * Send XMPP Message
     *
     * @param string $to
     * @param string $body
     * @param string $type (optional)
     * @param string|null $subject (optional)
     * @param string|null $payload (optional)
     * @throws Exception
     */
    public function message(string $to, string $body, string $type = 'chat', ?string $subject = null, ?string $payload = null): void
    {
        if ($this->disconnected) {
            throw new Exception('You need to connect first');
        }

        if (empty($type)) {
            $type = 'chat';
        }

        $to = htmlspecialchars($to);
        $body = htmlspecialchars($body);
        $subject = htmlspecialchars($subject);
        $subject = ($subject) ? '<subject>' . $subject . '</subject>' : '';
        $payload = ($payload) ? $payload : '';
        $sprintf = '<message from="%s" to="%s" type="%s">%s<body>%s</body>%s</message>';
        $output = sprintf($sprintf, $this->fulljid, $to, $type, $subject, $body, $payload);
        $this->send($output);
    }

    /**
     * Set Presence
     *
     * @param string $status
     * @param string $show
     * @param string $to
     * @param string $type
     * @param null $priority
     * @throws Exception
     */
    public function presence($status = null, $show = 'available', $to = null, $type = 'available', $priority = null): void
    {
        if ($this->disconnected) {
            throw new Exception('You need to connect first');
        }

        if ($type == 'available') {
            $type = '';
        }
        $to = htmlspecialchars($to);
        $status = htmlspecialchars($status);
        if ($show == 'unavailable') {
            $type = 'unavailable';
        }

        $out = "<presence";
        if ($to) {
            $out .= " to=\"$to\"";
        }
        if ($type) {
            $out .= " type='$type'";
        }
        if ($show == 'available' and !$status and $priority !== null) {
            $out .= "/>";
        } else {
            $out .= ">";
            if ($show != 'available') {
                $out .= "<show>$show</show>";
            }
            if ($status) {
                $out .= "<status>$status</status>";
            }
            if ($priority !== null) {
                $out .= "<priority>$priority</priority>";
            }
            $out .= "</presence>";
        }

        $this->send($out);
    }

    /**
     * Send Auth request
     *
     * @param string $jid
     * @throws Exception
     */
    public function subscribe(string $jid): void
    {
        $this->send("<presence type='subscribe' to='{$jid}' from='{$this->fulljid}' />");
        #$this->send("<presence type='subscribed' to='{$jid}' from='{$this->fulljid}' />");
    }

    /**
     * Message handler
     *
     * @param XMLObj $xml
     */
    public function message_handler(XMLObj $xml): void
    {
        if (isset($xml->attrs['type'])) {
            $payload['type'] = $xml->attrs['type'];
        } else {
            $payload['type'] = 'chat';
        }
        $body = $xml->sub('body');
        $payload['from'] = $xml->attrs['from'];
        $payload['body'] = is_object($body) ? $body->data : false; // $xml->sub('body')->data;
        $payload['xml'] = $xml;
        $this->log->log("Message: {$payload['body']}", Log::LEVEL_DEBUG);
        $this->event('message', $payload);
    }

    /**
     * Presence handler
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    public function presence_handler(XMLObj $xml): void
    {
        $payload['type'] = (isset($xml->attrs['type'])) ? $xml->attrs['type'] : 'available';
        $payload['show'] = (isset($xml->sub('show')->data)) ? $xml->sub('show')->data : $payload['type'];
        $payload['from'] = $xml->attrs['from'];
        $payload['status'] = (isset($xml->sub('status')->data)) ? $xml->sub('status')->data : '';
        $payload['priority'] = (isset($xml->sub('priority')->data)) ? intval($xml->sub('priority')->data) : 0;
        $payload['xml'] = $xml;
        if ($this->track_presence) {
            $this->roster->setPresence($payload['from'], $payload['priority'], $payload['show'], $payload['status']);
        }
        $this->log->log("Presence: {$payload['from']} [{$payload['show']}] {$payload['status']}", Log::LEVEL_DEBUG);
        if (array_key_exists('type', $xml->attrs) and $xml->attrs['type'] == 'subscribe') {
            if ($this->auto_subscribe) {
                $this->send("<presence type='subscribed' to='{$xml->attrs['from']}' from='{$this->fulljid}' />");
                $this->send("<presence type='subscribe' to='{$xml->attrs['from']}' from='{$this->fulljid}' />");
            }
            $this->event('subscription_requested', $payload);
        } elseif (array_key_exists('type', $xml->attrs) and $xml->attrs['type'] == 'subscribed') {
            $this->event('subscription_accepted', $payload);
        } else {
            $this->event('presence', $payload);
        }
    }

    /**
     * Retrieves the roster
     *
     * @throws Exception
     */
    public function getRoster(): void
    {
        $id = $this->getID();
        $this->send("<iq xmlns='jabber:client' type='get' id='$id'><query xmlns='jabber:iq:roster' /></iq>");
    }

    /**
     * Retrieves the vcard
     * @param string|null $jid
     * @throws Exception
     */
    public function getVCard(?string $jid = null): void
    {
        $id = $this->getId();
        $this->addIdHandler($id, [$this, 'vcard_get_handler']);
        if ($jid) {
            $this->send("<iq type='get' id='$id' to='$jid'><vCard xmlns='vcard-temp' /></iq>");
        } else {
            $this->send("<iq type='get' id='$id'><vCard xmlns='vcard-temp' /></iq>");
        }
    }

    /**
     * Features handler
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    protected function features_handler(XMLObj $xml): void
    {
        if ($xml->hasSub('starttls') and $this->use_encryption) {
            $this->send("<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'><required /></starttls>");
        } elseif ($xml->hasSub('bind') and $this->authed) {
            $id = $this->getId();
            $this->addIdHandler($id, [$this, 'resource_bind_handler']);
            $this->send("<iq xmlns=\"jabber:client\" type=\"set\" id=\"$id\"><bind xmlns=\"urn:ietf:params:xml:ns:xmpp-bind\"><resource>{$this->resource}</resource></bind></iq>");
        } else {
            $this->log->log("Attempting Auth...");
            if ($this->password) {
                $this->send("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='PLAIN'>" . base64_encode("\x00" . $this->user . "\x00" . $this->password) . "</auth>");
            } else {
                $this->send("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='ANONYMOUS'/>");
            }
        }
    }

    /**
     * SASL success handler
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    protected function sasl_success_handler(XMLObj $xml): void
    {
        $this->log->log("Auth success!");
        $this->authed = true;
        $this->reset();
    }

    /**
     * SASL feature handler
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    protected function sasl_failure_handler(XMLObj $xml): void
    {
        $this->log->log("Auth failed!", Log::LEVEL_ERROR);
        $this->disconnect();

        throw new Exception('Auth failed!');
    }

    /**
     * Resource bind handler
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    protected function resource_bind_handler(XMLObj $xml): void
    {
        if ($xml->attrs['type'] == 'result') {
            $this->log->log("Bound to " . $xml->sub('bind')->sub('jid')->data);
            $this->fulljid = $xml->sub('bind')->sub('jid')->data;
            $jidarray = explode('/', $this->fulljid);
            $this->jid = $jidarray[0];
        }
        $id = $this->getId();
        $this->addIdHandler($id, [$this, 'session_start_handler']);
        $this->send("<iq xmlns='jabber:client' type='set' id='$id'><session xmlns='urn:ietf:params:xml:ns:xmpp-session' /></iq>");
    }

    /**
     * Roster iq handler
     * Gets all packets matching XPath "iq/{jabber:iq:roster}query'
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    protected function roster_iq_handler(XMLObj $xml): void
    {
        $status = 'result';
        $xmlroster = $xml->sub('query');
        $contacts = [];
        foreach ($xmlroster->subs as $item) {
            $groups = [];
            if ($item->name === 'item') {
                $jid = $item->attrs['jid'];  // REQUIRED
                $name = $item->attrs['name'] ?? '';
                $subscription = $item->attrs['subscription'] ?? 'none';
                foreach ($item->subs as $subitem) {
                    if ($subitem->name === 'group') {
                        $groups[] = $subitem->data;
                    }
                }
                // Store for action if no errors happen
                $contacts[] = [$jid, $subscription, $name, $groups];
            } else {
                $status = 'error';
            }
        }
        // No errors, add contacts
        if ($status === 'result') {
            foreach ($contacts as $contact) {
                $this->roster->addContact(...$contact);
            }
        }
        if ($xml->attrs['type'] == 'set') {
            $this->send("<iq type=\"reply\" id=\"{$xml->attrs['id']}\" to=\"{$xml->attrs['from']}\" />");
        }
    }

    /**
     * Session start handler
     *
     * @param XMLObj $xml
     */
    protected function session_start_handler(XMLObj $xml): void
    {
        $this->log->log("Session started");
        $this->session_started = true;
        $this->event('session_start');
    }

    /**
     * TLS proceed handler
     *
     * @param XMLObj $xml
     * @throws Exception
     */
    protected function tls_proceed_handler(XMLObj $xml): void
    {
        $this->log->log("Starting TLS encryption");
        stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $this->reset();
    }

    /**
     * VCard retrieval handler
     *
     * @param XMLObj $xml
     */
    protected function vcard_get_handler(XMLObj $xml): void
    {
        $vcard_array = [];
        $vcard = $xml->sub('vcard');
        // go through all of the sub elements and add them to the vcard array
        foreach ($vcard->subs as $sub) {
            if ($sub->subs) {
                $vcard_array[$sub->name] = [];
                foreach ($sub->subs as $sub_child) {
                    $vcard_array[$sub->name][$sub_child->name] = $sub_child->data;
                }
            } else {
                $vcard_array[$sub->name] = $sub->data;
            }
        }
        $vcard_array['from'] = $xml->attrs['from'];
        $this->event('vcard', $vcard_array);
    }
}
