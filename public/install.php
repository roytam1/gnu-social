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
 * Web Installer
 *
 * @package   Installation
 * @author    Adrian Lang <mail@adrianlang.de>
 * @author    Brenda Wallace <shiny@cpan.org>
 * @author    Brett Taylor <brett@webfroot.co.nz>
 * @author    Brion Vibber <brion@pobox.com>
 * @author    CiaranG <ciaran@ciarang.com>
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Eric Helgeson <helfire@Erics-MBP.local>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @author    Robin Millette <millette@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Tom Adams <tom@holizz.com>
 * @author    Zach Copley <zach@status.net>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

define('INSTALLDIR', dirname(__DIR__));
define('PUBLICDIR', INSTALLDIR . DIRECTORY_SEPARATOR . 'public');

require INSTALLDIR . '/lib/util/installer.php';

/**
 * Helper class for building form
 */
class Posted
{
    /**
     * HTML-friendly escaped string for the POST param of given name, or empty.
     * @param string $name
     * @return string
     */
    public function value(string $name): string
    {
        return htmlspecialchars($this->string($name));
    }

    /**
     * The given POST parameter value, forced to a string.
     * Missing value will give ''.
     *
     * @param string $name
     * @return string
     */
    public function string(string $name): string
    {
        return strval($this->raw($name));
    }

    /**
     * The given POST parameter value, in its original form.
     * Missing value will give null.
     *
     * @param string $name
     * @return mixed
     */
    public function raw(string $name)
    {
        return filter_input(INPUT_POST, $name);
    }
}

/**
 * Web-based installer: provides a form and such.
 */
class WebInstaller extends Installer
{
    /**
     * the actual installation.
     * If call libraries are present, then install
     *
     * @return void
     */
    public function main()
    {
        if (!$this->checkPrereqs()) {
            $this->warning(_('Please fix the above stated problems and refresh this page to continue installing.'));
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            $this->showForm();
        }
    }

    /**
     * Web implementation of warning output
     * @param string $message
     * @param string $submessage
     */
    public function warning(string $message, string $submessage = '')
    {
        print "<p class=\"error\">$message</p>\n";
        if ($submessage != '') {
            print "<p>$submessage</p>\n";
        }
    }

    /**
     * Web implementation of status output
     * @param string $status
     * @param bool $error
     */
    public function updateStatus(string $status, bool $error = false)
    {
        echo '<li' . ($error ? ' class="error"' : '') . ">$status</li>";
    }

    /**
     * Show the web form!
     */
    public function showForm()
    {
        global $dbModules;
        $post = new Posted();
        $dbRadios = '';
        $dbtype = $post->raw('dbtype');
        foreach (self::$dbModules as $type => $info) {
            if (extension_loaded($info['check_module'])) {
                if ($dbtype == null || $dbtype == $type) {
                    $checked = 'checked="checked" ';
                    $dbtype = $type; // if we didn't have one checked, hit the first
                } else {
                    $checked = '';
                }
                $dbRadios .= sprintf(
                    '<input type="radio" name="dbtype" id="dbtype-%1$s" value="%1$s" %2$s/>%3$s<br>',
                    htmlspecialchars($type),
                    $checked,
                    htmlspecialchars($info['name'])
                );
            }
        }

        $ssl = ['always' => null, 'never' => null];
        if (!empty($_SERVER['HTTPS'])) {
            $ssl['always'] = 'checked="checked"';
        } else {
            $ssl['never'] = 'checked="checked"';
        }

        echo <<<E_O_T
    <form method="post" action="install.php" class="form_settings" id="form_install">
        <fieldset>
            <fieldset id="settings_site">
                <legend>Site settings</legend>
                <ul class="form_data">
                    <li>
                        <label for="sitename">Site name</label>
                        <input type="text" id="sitename" name="sitename" value="{$post->value('sitename')}">
                        <p class="form_guide">The name of your site</p>
                    </li>
                    <li>
                        <label for="fancy-enable">Fancy URLs</label>
                        <input type="radio" name="fancy" id="fancy-enable" value="enable" checked='checked'> enable<br>
                        <input type="radio" name="fancy" id="fancy-disable" value=""> disable<br>
                        <p class="form_guide" id='fancy-form_guide'>Enable fancy (pretty) URLs. Auto-detection failed, it depends on Javascript.</p>
                    </li>
                    <li>
                        <label for="ssl">Server SSL</label>
                        <input type="radio" name="ssl" id="ssl-always" value="always" {$ssl['always']}> enable<br>
                        <input type="radio" name="ssl" id="ssl-never" value="never" {$ssl['never']}> disable<br>
                        <input type="radio" name="ssl" id="ssl-proxy" value="proxy"> proxied<br>
                        <p class="form_guide" id="ssl-form_guide">Enabling SSL (https://) requires extra webserver configuration and certificate generation not offered by this installation.</p>
                    </li>
                </ul>
            </fieldset>

            <fieldset id="settings_db">
                <legend>Database settings</legend>
                <ul class="form_data">
                    <li>
                        <label for="host">Hostname</label>
                        <input type="text" id="host" name="host" value="{$post->value('host')}">
                        <p class="form_guide">Database hostname</p>
                    </li>
                    <li>
                        <label for="dbtype">Type</label>
                        {$dbRadios}
                        <p class="form_guide">Database type</p>
                    </li>
                    <li>
                        <label for="database">Name</label>
                        <input type="text" id="database" name="database" value="{$post->value('database')}">
                        <p class="form_guide">Database name</p>
                    </li>
                    <li>
                        <label for="dbusername">DB username</label>
                        <input type="text" id="dbusername" name="dbusername" value="{$post->value('dbusername')}">
                        <p class="form_guide">Database username</p>
                    </li>
                    <li>
                        <label for="dbpassword">DB password</label>
                        <input type="password" id="dbpassword" name="dbpassword" value="{$post->value('dbpassword')}">
                        <p class="form_guide">Database password (optional)</p>
                    </li>
                </ul>
            </fieldset>

            <fieldset id="settings_admin">
                <legend>Administrator settings</legend>
                <ul class="form_data">
                    <li>
                        <label for="admin_nickname">Administrator nickname</label>
                        <input type="text" id="admin_nickname" name="admin_nickname" value="{$post->value('admin_nickname')}">
                        <p class="form_guide">Nickname for the initial user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_password">Administrator password</label>
                        <input type="password" id="admin_password" name="admin_password" value="{$post->value('admin_password')}">
                        <p class="form_guide">Password for the initial user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_password2">Confirm password</label>
                        <input type="password" id="admin_password2" name="admin_password2" value="{$post->value('admin_password2')}">
                    </li>
                    <li>
                        <label for="admin_email">Administrator e-mail</label>
                        <input id="admin_email" name="admin_email" value="{$post->value('admin_email')}">
                        <p class="form_guide">Optional email address for the initial user (administrator)</p>
                    </li>
                </ul>
            </fieldset>
            <fieldset id="settings_profile">
                <legend>Site profile</legend>
                <ul class="form_data">
                    <li>
                        <label for="site_profile">Type of site</label>
                        <select id="site_profile" name="site_profile">
                            <option value="community">Community</option>
                            <option value="public">Public (open registration)</option>
                            <option value="singleuser">Single User</option>
                            <option value="private">Private (no federation)</option>
                        </select>
                        <p class="form_guide">Initial access settings for your site</p>
                    </li>
                </ul>
            </fieldset>
            <input type="submit" name="submit" class="submit" value="Submit">
        </fieldset>
    </form>

E_O_T;
    }

    /**
     * Handle a POST submission... if we have valid input, start the install!
     * Otherwise shows the form along with any error messages.
     */
    public function handlePost()
    {
        echo <<<STR
        <dl class="system_notice">
            <dt>Page notice</dt>
            <dd>
                <ul>
STR;
        $this->validated = $this->prepare();
        if ($this->validated) {
            $this->doInstall();
        }
        echo <<<STR
            </ul>
        </dd>
    </dl>
STR;
        if (!$this->validated) {
            $this->showForm();
        }
    }

    /**
     * Read and validate input data.
     * May output side effects.
     *
     * @return bool success
     */
    public function prepare(): bool
    {
        $post = new Posted();
        $this->host = $post->string('host');
        $this->dbtype = $post->string('dbtype');
        $this->database = $post->string('database');
        $this->username = $post->string('dbusername');
        $this->password = $post->string('dbpassword');
        $this->sitename = $post->string('sitename');
        $this->fancy = (bool)$post->string('fancy');

        $this->adminNick = strtolower($post->string('admin_nickname'));
        $this->adminPass = $post->string('admin_password');
        $adminPass2 = $post->string('admin_password2');
        $this->adminEmail = $post->string('admin_email');

        $this->siteProfile = $post->string('site_profile');

        $this->ssl = $post->string('ssl');

        $this->server = $_SERVER['HTTP_HOST'];
        $this->path = substr(dirname($_SERVER['PHP_SELF']), 1);

        $fail = false;
        if (!$this->validateDb()) {
            $fail = true;
        }

        if (!$this->validateAdmin()) {
            $fail = true;
        }

        if ($this->adminPass != $adminPass2) {
            $this->updateStatus("Administrator passwords do not match. Did you mistype?", true);
            $fail = true;
        }

        if (!in_array($this->ssl, ['never', 'always', 'proxy'])) {
            $this->updateStatus("Bad value for server SSL enabling.");
            $fail = true;
        }

        if (!$this->validateSiteProfile()) {
            $fail = true;
        }

        return !$fail;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Install GNU social</title>
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="stylesheet" type="text/css" href="theme/base/css/display.css" media="screen, projection, tv">
    <link rel="stylesheet" type="text/css" href="theme/neo/css/display.css" media="screen, projection, tv">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <script src="js/extlib/jquery.js"></script>
    <script src="js/install.js"></script>
</head>
<body id="install">
<div id="wrap">
    <div id="header">
        <address id="site_contact" class="h-card">
            <a class="u-url p-name home bookmark org" href=".">
                <img class="logo u-photo" src="theme/neo/logo.png" alt="GNU social"/>
                GNU social
            </a>
        </address>
        <div id="site_nav_global_primary"></div>
    </div>
    <div id="core">
        <div id="aside_primary_wrapper">
            <div id="content_wrapper">
                <div id="site_nav_local_views_wrapper">
                    <div id="site_nav_local_views"></div>

                    <div id="content">
                        <div id="content_inner">
                            <h1>Install GNU social</h1>
                            <?php
                            $installer = new WebInstaller();
                            $installer->main();
                            ?>
                        </div>
                    </div>

                    <div id="aside_primary" class="aside"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="footer"></div>
</div>
</body>
</html>
