<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to push RSS/Atom updates to a PubSubHubBub hub
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class TightUrlPlugin extends UrlShortenerPlugin
{
    const PLUGIN_VERSION = '2.0.0';

    public $serviceUrl;

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->serviceUrl)){
            // TRANS: Exception thrown when the TightUrl plugin has been configured incorrectly.
            throw new Exception(_m('You must specify a serviceUrl.'));
        }
    }

    protected function shorten($url)
    {
        $response = $this->http_get(sprintf($this->serviceUrl,urlencode($url)));
        if (!$response) return;
        $dom = new DOMDocument();
        @$dom->loadHTML($response);
        $y = @simplexml_import_dom($dom);
        if (!isset($y->body)) return;
        $xml = $y->body->p[0]->code[0]->a->attributes();
        if (isset($xml['href'])) {
            return strval($xml['href']);
        }
    }

    public function onPluginVersion(array &$versions): bool
    {
        $versions[] = array('name' => sprintf('TightUrl (%s)', $this->shortenerName),
                            'version' => self::PLUGIN_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => GNUSOCIAL_ENGINE_REPO_URL . 'tree/master/plugins/TightUrl',
                            'rawdescription' =>
                            // TRANS: Plugin description. %s is the shortener name.
                            sprintf(_m('Uses <a href="http://%1$s/">%1$s</a> URL-shortener service.'),
                                    $this->shortenerName));
        return true;
    }
}
