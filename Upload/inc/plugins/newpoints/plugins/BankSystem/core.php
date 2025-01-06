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

use const Newpoints\BankSystem\ROOT;

const TRANSACTION_TYPE_DEPOSIT = 1;

const TRANSACTION_TYPE_WITHDRAW = 2;

const TRANSACTION_STATUS_LIVE = 1;

const TRANSACTION_STATUS_CANCELLED = 2;

const INTEREST_PERIOD_TYPE_DAY = 1;

const INTEREST_PERIOD_TYPE_WEEK = 2;

const INTEREST_PERIOD_TYPE_MONTH = 3;

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