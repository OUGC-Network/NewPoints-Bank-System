<?php

/***************************************************************************
 *
 *    NewPoints Bank System plugin (/inc/plugins/BankSystem/core.php)
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

namespace Newpoints\BankSystem\Core;

use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\BankSystem\ROOT;

const TRANSACTION_TYPE_DEPOSIT = 1;

const TRANSACTION_TYPE_WITHDRAW = 2;

const TRANSACTION_TYPE_INTEREST = 3;

const TRANSACTION_STATUS_LIVE = 1;

const TRANSACTION_STATUS_CANCELLED = 2;

const TRANSACTION_STATUS_NO_FUNDS = 3;

const TRANSACTION_COMPLETE_STATUS_NEW = 0;

const TRANSACTION_COMPLETE_STATUS_PROCESSED = 1;

const TRANSACTION_COMPLETE_STATUS_LOGGED = 2;

const INTEREST_PERIOD_TYPE_DAY = 1;

const INTEREST_PERIOD_TYPE_WEEK = 2;

function templates_get(string $template_name = '', bool $enable_html_comments = true): string
{
    return \Newpoints\Core\templates_get($template_name, $enable_html_comments, ROOT, 'bank_system_');
}

function execute_task(): bool
{
    return true;
}

function user_get_balance(int $user_id): float
{
    global $db;

    $query = $db->simple_select(
        'users',
        'newpoints_bank',
        "user_id='{$user_id}'"
    );

    return (float)$db->fetch_field($query, 'newpoints_bank');
}

function can_manage(): bool
{
    return false;
}

function can_create_transaction(int $user_id): bool
{
    $user_group_permissions = users_get_group_permissions($user_id);

    return !empty($user_group_permissions['newpoints_bank_system_can_deposit']) ||
        !empty($user_group_permissions['newpoints_bank_system_can_withdraw']);
}

function transaction_insert(array $transaction_data, bool $is_update = false, int $transaction_id = 0): int
{
    global $db;

    $insert_data = [];

    if (isset($transaction_data['user_id'])) {
        $insert_data['user_id'] = (int)$transaction_data['user_id'];
    }

    if (isset($transaction_data['transaction_type'])) {
        $insert_data['transaction_type'] = (int)$transaction_data['transaction_type'];
    }

    if (isset($transaction_data['transaction_points'])) {
        $insert_data['transaction_points'] = (float)$transaction_data['transaction_points'];
    }

    if (isset($transaction_data['transaction_stamp'])) {
        $insert_data['transaction_stamp'] = (int)$transaction_data['transaction_stamp'];
    } else {
        $insert_data['transaction_stamp'] = TIME_NOW;
    }

    if (isset($transaction_data['transaction_status'])) {
        $insert_data['transaction_status'] = (int)$transaction_data['transaction_status'];
    }

    if (isset($transaction_data['complete_status'])) {
        $insert_data['complete_status'] = (int)$transaction_data['complete_status'];
    }

    return (int)$db->insert_query('newpoints_bank_system_transactions', $insert_data);
}