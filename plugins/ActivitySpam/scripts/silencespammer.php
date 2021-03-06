#!/usr/bin/env php
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
 * @copyright 2013 StatusNet, Inc.
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

define('INSTALLDIR', dirname(__DIR__, 3));
define('PUBLICDIR', INSTALLDIR . DIRECTORY_SEPARATOR . 'public');

$shortoptions = 'i:n:a';
$longoptions = array('id=', 'nickname=', 'all');

$helptext = <<<END_OF_SILENCESPAMMER_HELP
silencespammer.php [options]
Users who post a lot of spam get silenced

  -i --id       ID of user to test and silence
  -n --nickname nickname of the user to test and silence
  -a --all      All users
END_OF_SILENCESPAMMER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function testAllUsers($filter, $minimum, $percent)
{
    $found = false;
    $offset = 0;
    $limit  = 1000;

    do {
        $user = new User();
        $user->orderBy('created, id');
        $user->limit($offset, $limit);

        $found = $user->find();

        if ($found) {
            while ($user->fetch()) {
                try {
                    silencespammer($filter, $user, $minimum, $percent);
                } catch (Exception $e) {
                    printfnq("ERROR testing user %s\n: %s", $user->nickname, $e->getMessage());
                }
            }
            $offset += $found;
        }
    } while ($found > 0);
}

function silencespammer($filter, $user, $minimum, $percent)
{
    printfnq("Testing user %s\n", $user->nickname);

    $profile = Profile::getKV('id', $user->id);

    if ($profile->isSilenced()) {
        printfnq("Already silenced %s\n", $user->nickname);
        return;
    }
    
    $cnt = $profile->noticeCount();

    if ($cnt < $minimum) {
        printfnq("Only %d notices posted (minimum %d); skipping\n", $cnt, $minimum);
        return;
    }

    $ss = new Spam_score();

    $ss->query(sprintf(
        <<<'END'
        SELECT COUNT(*) AS spam_count
          FROM notice INNER JOIN spam_score ON notice.id = spam_score.notice_id
          WHERE notice.profile_id = %d AND spam_score.is_spam IS TRUE;
        END,
        $profile->getID()
    ));

    while ($ss->fetch()) {
        $spam_count = $ss->spam_count;
    }

    $spam_percent = ($spam_count * 100.0 / $cnt);

    if ($spam_percent > $percent) {
        printfnq("Silencing user %s (%d/%d = %0.2f%% spam)\n", $user->nickname, $spam_count, $cnt, $spam_percent);
        try {
            $profile->silence();
        } catch (Exception $e) {
            printfnq("Error: %s", $e->getMessage());
        }
    }
}

try {
    $filter = null;
    $minimum = 5;
    $percent = 80;
    Event::handle('GetSpamFilter', array(&$filter));
    if (empty($filter)) {
        throw new Exception(_("No spam filter."));
    }
    if (have_option('a', 'all')) {
        testAllUsers($filter, $minimum, $percent);
    } else {
        $user = getUser();
        silencespammer($filter, $user, $minimum, $percent);
    }
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
