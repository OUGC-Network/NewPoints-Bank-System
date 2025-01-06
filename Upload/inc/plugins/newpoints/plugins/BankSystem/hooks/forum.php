<?php

/***************************************************************************
 *
 *    NewPoints Bank System plugin (/inc/plugins/BankSystem/hooks/forum.php)
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

namespace Newpoints\BankSystem\Hooks\Forum;

use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\BankSystem\Core\templates_get;

function newpoints_global_start(array &$hook_arguments): array
{
    $hook_arguments['newpoints.php'] = array_merge($hook_arguments['newpoints.php'], [
        'newpoints_bank_system_page_table_transactions_row',
        'newpoints_bank_system_page_table_transactions',
    ]);

    return $hook_arguments;
}

function newpoints_default_menu(array &$menu): array
{
    global $mybb;

    if (!empty($mybb->usergroup['newpoints_bank_system_can_view'])) {
        language_load('bank_system');

        $menu[get_setting('bank_system_menu_order')] = [
            'action' => get_setting('bank_system_action_name'),
            'lang_string' => 'newpoints_bank_system_menu'
        ];
    }

    return $menu;
}

function newpoints_home_end(): bool
{
    if (!($limit = (int)get_setting('bank_system_home_transactions'))) {
        return false;
    }

    global $mybb, $db, $lang, $theme;
    global $latest_transactions;

    language_load('bank_system');

    $current_user_id = (int)$mybb->user['uid'];

    $where_clauses = [
        "user_id='{$current_user_id}'"
    ];

    $query = $db->simple_select(
        'newpoints_bank_system_transactions',
        'transaction_id, transaction_type, transaction_points, transaction_stamp',
        implode(' AND ', $where_clauses),
        ['order_by' => 'transaction_stamp', 'order_dir' => 'DESC', 'limit' => $limit]
    );

    if (!$db->num_rows($query)) {
        return false;
    }

    $alternative_background = alt_trow(true);

    $transactions_list = '';

    while ($transaction_data = $db->fetch_array($query)) {
        $transaction_id = my_number_format($transaction_data['transaction_id']);

        $transaction_type = (int)$transaction_data['transaction_type'];

        $transaction_points = (float)$transaction_data['transaction_points'];

        $transaction_stamp = my_date('normal', $transaction_data['transaction_stamp']);

        $transactions_list .= eval(templates_get('page_table_transactions_row'));

        $alternative_background = alt_trow();
    }

    $latest_transactions[] = eval(templates_get('page_table_transactions'));

    return true;
}

function newpoints_logs_log_row(): bool
{
    global $log_data;

    if (!in_array($log_data['action'], [
    ])) {
        return false;
    }

    global $lang;
    global $log_action, $log_primary, $log_secondary, $log_tertiary;

    language_load('bank_system');

    return true;
}

function newpoints_logs_end(): bool
{
    global $lang;
    global $action_types;

    language_load('bank_system');

    foreach ($action_types as $key => &$action_type) {
    }

    return true;
}