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

/*
 * @copyright 2008, 2009 StatusNet, Inc.
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

use XMPPHP\Log;

/**
 * XMPP background connection manager for XMPP-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, keepalive pings during idle.
 * Any incoming messages will be handled.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has XMPP enabled.
 */
class XmppManager extends ImManager
{
    const PING_INTERVAL = 120;
    public $conn = null;
    protected $lastping = 0;
    protected $pingid = 0;

    /**
     * Initialize connection to server.
     * @param $master
     * @return boolean true on success
     */
    public function start($master)
    {
        if (parent::start($master)) {
            $this->connect();
            return true;
        } else {
            return false;
        }
    }

    protected function connect()
    {
        if (!$this->conn || $this->conn->isDisconnected()) {
            $resource = 'queue' . posix_getpid();
            $this->conn = new SharingXMPP(
                $this->plugin->host ?: $this->plugin->server,
                $this->plugin->port,
                $this->plugin->user,
                $this->plugin->password,
                $this->plugin->resource,
                $this->plugin->server,
                $this->plugin->debug ?
                    true : false,
                $this->plugin->debug ?
                    Log::LEVEL_VERBOSE : null
            );

            if (!$this->conn) {
                return false;
            }
            $this->conn->addEventHandler('message', function (&$pl) {
                $this->handleXmppMessage($pl);
            });
            $this->conn->addEventHandler('reconnect', function ($pl) {
                $this->handleXmppReconnect();
            });
            $this->conn->addXPathHandler(
                'iq/{urn:xmpp:ping}ping',
                function (&$xml) {
                    $this->handleXmppPing($xml);
                }
            );
            $this->conn->setReconnectTimeout(600);

            $this->conn->autoSubscribe();
            $this->conn->useEncryption($this->plugin->encryption);

            $this->conn->connect(true);

            $this->conn->processUntil('session_start');
            // TRANS: Presence announcement for XMPP.
            $this->sendPresence(
                _m('Send me a message to post a notice'),
                'available',
                null,
                'available',
                100
            );
        }
        return $this->conn;
    }

    /**
     * sends a presence stanza on the XMPP network
     *
     * @param string|null $status current status, free-form string
     * @param string $show structured status value
     * @param string|null $to recipient of presence, null for general
     * @param string $type type of status message, related to $show
     * @param int|null $priority priority of the presence
     *
     * @return bool success value
     */
    protected function sendPresence(
        ?string $status,
        string $show = 'available',
        ?string $to = null,
        string $type = 'available',
        ?int $priority = null
    ): bool {
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }
        $this->conn->presence($status, $show, $to, $type, $priority);
        return true;
    }

    public function send_raw_message($data)
    {
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }
        $this->conn->send($data);
        return true;
    }

    /**
     * Message pump is triggered on socket input, so we only need an idle()
     * call often enough to trigger our outgoing pings.
     */
    public function timeout()
    {
        return self::PING_INTERVAL;
    }

    /**
     * Process XMPP events that have come in over the wire.
     * @fixme may kill process on XMPP error
     * @param resource $socket
     */
    public function handleInput($socket)
    {
        // Process the queue for as long as needed
        common_log(LOG_DEBUG, "Servicing the XMPP queue.");
        $this->stats('xmpp_process');
        $this->conn->processTime(0);
    }

    /**
     * Lists the IM connection socket to allow i/o master to wake
     * when input comes in here as well as from the queue source.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        $this->connect();
        if ($this->conn) {
            return array($this->conn->getSocket());
        } else {
            return array();
        }
    }

    /**
     * Idle processing for io manager's execution loop.
     * Send keepalive pings to server.
     *
     * Side effect: kills process on exception from XMPP library.
     *
     * @param int $timeout
     * @todo FIXME: non-dying error handling
     */
    public function idle($timeout = 0)
    {
        if (
            hrtime(true) - $this->lastping > self::PING_INTERVAL * 1000000000
        ) {
            $this->sendPing();
        }
    }

    protected function sendPing(): bool
    {
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }
        ++$this->pingid;

        common_log(LOG_DEBUG, "Sending ping #{$this->pingid}");
        $this->conn->send("<iq from='{$this->plugin->daemonScreenname()}' to='{$this->plugin->server}' id='ping_{$this->pingid}' type='get'><ping xmlns='urn:xmpp:ping'/></iq>");
        $this->lastping = hrtime(true);
        return true;
    }

    protected function handleXmppMessage($pl): void
    {
        $this->plugin->enqueueIncomingRaw($pl);
    }

    /**
     * Callback for the XMPP reconnect event
     * @return void
     */
    protected function handleXmppReconnect(): void
    {
        common_log(LOG_NOTICE, 'XMPP reconnected');

        $this->conn->processUntil('session_start');
        // TRANS: Message for XMPP reconnect.
        $this->sendPresence(
            _m('Send me a message to post a notice'),
            'available',
            null,
            'available',
            100
        );
    }

    protected function handleXmppPing($xml): void
    {
        if ($xml->attrs['type'] !== 'get') {
            return;
        }

        $this->conn->send(
            "<iq from=\"{$xml->attrs['to']}\" to=\"{$xml->attrs['from']}\" "
            . "id=\"{$xml->attrs['id']}\" type=\"result\" />"
        );
    }

    /**
     * sends a "special" presence stanza on the XMPP network
     *
     * @param string $type Type of presence
     * @param string $to JID to send presence to
     * @param string $show show value for presence
     * @param string $status status value for presence
     *
     * @return bool success flag
     *
     * @see sendPresence()
     */

    protected function specialPresence(
        string $type,
        ?string $to = null,
        ?string $show = null,
        ?string $status = null
    ): bool {
        // @todo @fixme Why use this instead of sendPresence()?
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }

        $to = htmlspecialchars($to);
        $status = htmlspecialchars($status);

        $out = "<presence";
        if ($to) {
            $out .= " to='$to'";
        }
        if ($type) {
            $out .= " type='$type'";
        }
        if ($show == 'available' and !$status) {
            $out .= "/>";
        } else {
            $out .= ">";
            if ($show && ($show != 'available')) {
                $out .= "<show>$show</show>";
            }
            if ($status) {
                $out .= "<status>$status</status>";
            }
            $out .= "</presence>";
        }
        $this->conn->send($out);
        return true;
    }
}
