<?php

/***************************************************************************
 *
 *    NewPoints Bank System plugin (/inc/plugins/BankSystem/hooks/admin.php)
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

namespace Newpoints\BankSystem\Hooks\Admin;

use function Newpoints\Core\language_load;

use const Newpoints\BankSystem\Admin\FIELDS_DATA;
use const Newpoints\BankSystem\ROOT;

function newpoints_settings_rebuild_start(array &$hook_arguments): array
{
    language_load('bank_system');

    $hook_arguments['settings_directories'][] = ROOT . '/settings';

    return $hook_arguments;
}

function newpoints_templates_rebuild_start(array $hook_arguments): array
{
    $hook_arguments['templates_directories']['bank_system'] = ROOT . '/templates';

    return $hook_arguments;
}

function newpoints_admin_settings_intermediate(array &$hook_arguments): array
{
    language_load('bank_system');

    //unset($hook_arguments['active_plugins']['newpoints_bank_system']);

    $hook_arguments['bank_system'] = [];

    return $hook_arguments;
}

function newpoints_admin_settings_commit_start(array &$setting_groups_objects): array
{
    return newpoints_admin_settings_intermediate($setting_groups_objects);
}

function newpoints_admin_user_groups_edit_graph_start(array &$hook_arguments): array
{
    language_load('bank_system');

    $hook_arguments['data_fields'] = array_merge(
        $hook_arguments['data_fields'],
        FIELDS_DATA['usergroups']
    );

    return $hook_arguments;
}

function newpoints_admin_user_groups_edit_commit_start(array &$hook_arguments): array
{
    return newpoints_admin_user_groups_edit_graph_start($hook_arguments);
}

// todo: maybe allow editing of bank points from the admin panel?