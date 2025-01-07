<?php

/***************************************************************************
 *
 *    NewPoints Bank System plugin (/inc/plugins/newpoints_bank_system.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2025 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to safeguard their points in a bank system.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

use function Newpoints\BankSystem\Admin\plugin_information;
use function Newpoints\BankSystem\Admin\plugin_activation;
use function Newpoints\BankSystem\Admin\plugin_deactivation;
use function Newpoints\BankSystem\Admin\plugin_is_installed;
use function Newpoints\BankSystem\Admin\plugin_uninstallation;
use function Newpoints\Core\add_hooks;

use const Newpoints\BankSystem\ROOT;
use const Newpoints\ROOT_PLUGINS;

defined('IN_MYBB') || die('Direct initialization of this file is not allowed.');

define('Newpoints\BankSystem\ROOT', ROOT_PLUGINS . '/BankSystem');

require_once ROOT . '/core.php';

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    add_hooks('Newpoints\BankSystem\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    add_hooks('Newpoints\BankSystem\Hooks\Forum');
}

function newpoints_bank_system_info(): array
{
    return plugin_information();
}

function newpoints_bank_system_activate(): bool
{
    return plugin_activation();
}

function newpoints_bank_system_deactivate(): bool
{
    return plugin_deactivation();
}

function newpoints_bank_system_uninstall(): bool
{
    return plugin_uninstallation();
}

function newpoints_bank_system_is_installed(): bool
{
    return plugin_is_installed();
}

(function () {
    global $groupzerolesser, $grouppermbyswitch;

    $groupzerolesser[] = 'newpoints_bank_system_withdraw';

    $grouppermbyswitch['newpoints_bank_system_withdraw'] = 'newpoints_bank_system_can_view';

    // the following is so days has priority over weeks, and weeks over months
    $groupzerolesser[] = 'newpoints_bank_system_interest_period_type';

    $grouppermbyswitch['newpoints_bank_system_interest_period_type'] = 'newpoints_bank_system_can_invest';
})();