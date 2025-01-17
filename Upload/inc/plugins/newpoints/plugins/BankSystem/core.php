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

use function Newpoints\Core\log_add;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\BankSystem\ROOT;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;

const TRANSACTION_TYPE_DEPOSIT = 1;

const TRANSACTION_TYPE_INVESTMENT = 2;

const TRANSACTION_TYPE_WITHDRAW = 3;

const TRANSACTION_TYPE_INTEREST = 4;

const TRANSACTION_INVESTMENT_TYPE_NOT_RECURRING = 0;

const TRANSACTION_INVESTMENT_TYPE_RECURRING = 1;

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
    global $db;

    $complete_status_new = TRANSACTION_COMPLETE_STATUS_NEW;

    $complete_status_processed = TRANSACTION_COMPLETE_STATUS_PROCESSED;

    $complete_status_logged = TRANSACTION_COMPLETE_STATUS_LOGGED;

    $transaction_status_live = TRANSACTION_STATUS_LIVE;

    $transaction_status_cancelled = TRANSACTION_STATUS_CANCELLED;

    $transaction_status_no_funds = TRANSACTION_STATUS_NO_FUNDS;

    $where_clauses = [
        'complete_status' => "complete_status IN ('{$complete_status_new}', '{$complete_status_processed}')",
        'transaction_status' => "transaction_status='{$transaction_status_live}'",
    ];

    $transaction_objects = transaction_get_multiple($where_clauses, [
        'transaction_id',
        'user_id',
        'transaction_type',
        'transaction_points',
        'transaction_fee',
        'investment_stamp',
        'complete_status',
    ], ['order_by' => 'transaction_stamp', 'asc' => 'desc', 'limit' => 10]);

    foreach ($transaction_objects as $transaction_data) {
        $transaction_id = (int)$transaction_data['transaction_id'];

        $user_id = (int)$transaction_data['user_id'];

        $user_data = get_user($user_id);

        $transaction_type = (int)$transaction_data['transaction_type'];

        $complete_status = (int)$transaction_data['complete_status'];

        $transaction_points = (float)$transaction_data['transaction_points'];

        $transaction_fee = (float)$transaction_data['transaction_fee'];

        if ($complete_status === $complete_status_new) {
            if ($transaction_type === TRANSACTION_TYPE_DEPOSIT) {
                if ($user_data['newpoints'] >= $transaction_points + $transaction_fee) {
                    points_subtract($user_id, $transaction_points + $transaction_fee);

                    transaction_update(['complete_status' => $complete_status_processed], $transaction_id);

                    $complete_status = $complete_status_processed;
                } else {
                    transaction_update(['transaction_status' => $transaction_status_no_funds], $transaction_id);
                }
            } elseif ($transaction_type === TRANSACTION_TYPE_INVESTMENT) {
                if ($user_data['newpoints'] >= $transaction_points + $transaction_fee) {
                    points_subtract($user_id, $transaction_points + $transaction_fee);

                    transaction_update([
                        'complete_status' => $complete_status_processed,
                        'investment_stamp' => TIME_NOW,
                        'investment_execution_stamp' => TIME_NOW
                    ], $transaction_id);

                    $complete_status = $complete_status_processed;
                } else {
                    transaction_update(['transaction_status' => $transaction_status_no_funds], $transaction_id);
                }
            } elseif ($transaction_type === TRANSACTION_TYPE_WITHDRAW) {
                if ($user_data['newpoints_bank'] >= $transaction_points && $user_data['newpoints'] >= $transaction_fee) {
                    points_add_simple($user_id, $transaction_points);

                    points_subtract($user_id, $transaction_fee);

                    transaction_update(['complete_status' => $complete_status_processed], $transaction_id);

                    $complete_status = $complete_status_processed;
                } else {
                    transaction_update(['transaction_status' => $transaction_status_no_funds], $transaction_id);
                }
            }
        }

        if ($complete_status === $complete_status_processed) {
            if ($transaction_type === TRANSACTION_TYPE_DEPOSIT) {
                log_add(
                    'bank_system_deposit',
                    '',
                    $user_data['username'] ?? '',
                    $user_id,
                    $transaction_points,
                    $transaction_id,
                    0,
                    0,
                    LOGGING_TYPE_CHARGE
                );

                if ($transaction_fee) {
                    log_add(
                        'bank_system_deposit_fee',
                        '',
                        $user_data['username'] ?? '',
                        $user_id,
                        $transaction_fee,
                        $transaction_id,
                        0,
                        0,
                        LOGGING_TYPE_CHARGE
                    );
                }

                transaction_update(['complete_status' => TRANSACTION_COMPLETE_STATUS_LOGGED], $transaction_id);
            } elseif ($transaction_type === TRANSACTION_TYPE_INVESTMENT) {
                log_add(
                    'bank_system_investment',
                    '',
                    $user_data['username'] ?? '',
                    $user_id,
                    $transaction_points,
                    $transaction_id,
                    0,
                    0,
                    LOGGING_TYPE_CHARGE
                );

                if ($transaction_fee) {
                    log_add(
                        'bank_system_investment_fee',
                        '',
                        $user_data['username'] ?? '',
                        $user_id,
                        $transaction_fee,
                        $transaction_id,
                        0,
                        0,
                        LOGGING_TYPE_CHARGE
                    );
                }

                transaction_update(['complete_status' => TRANSACTION_COMPLETE_STATUS_LOGGED], $transaction_id);
            } elseif ($transaction_type === TRANSACTION_TYPE_WITHDRAW) {
                log_add(
                    'bank_system_withdraw',
                    '',
                    $user_data['username'] ?? '',
                    $user_id,
                    $transaction_points,
                    $transaction_id,
                    0,
                    0,
                    LOGGING_TYPE_INCOME
                );

                if ($transaction_fee) {
                    log_add(
                        'bank_system_withdraw_fee',
                        '',
                        $user_data['username'] ?? '',
                        $user_id,
                        $transaction_fee,
                        $transaction_id,
                        0,
                        0,
                        LOGGING_TYPE_CHARGE
                    );
                }

                transaction_update(['complete_status' => TRANSACTION_COMPLETE_STATUS_LOGGED], $transaction_id);
            }
        }

        user_rebuild_bank_details($user_id);
    }

    $transaction_type_investment = TRANSACTION_TYPE_INVESTMENT;

    $where_clauses['complete_status'] = "complete_status='{$complete_status_logged}'";

    $where_clauses['transaction_type'] = "transaction_type='{$transaction_type_investment}'";

    $transaction_objects = transaction_get_multiple($where_clauses, [
        'transaction_id',
        'user_id',
        'transaction_points',
        'investment_type',
        'investment_stamp',
    ], ['order_by' => 'investment_execution_stamp', 'order_dir' => 'asc', 'limit' => 10]);

    $update_data = ['investment_execution_stamp' => TIME_NOW];

    foreach ($transaction_objects as $transaction_data) {
        $transaction_id = (int)$transaction_data['transaction_id'];

        $user_id = (int)$transaction_data['user_id'];

        $user_group_permissions = users_get_group_permissions($user_id);

        if (empty($user_group_permissions['newpoints_bank_system_can_invest'])) {
            transaction_update($update_data, $transaction_id);

            continue;
        }

        $transaction_points = (float)$transaction_data['transaction_points'];

        $interest_rate = (float)$user_group_permissions['newpoints_rate_bank_system_interest'];

        $interest_period = (float)$user_group_permissions['newpoints_bank_system_interest_period'];

        if (empty($interest_rate) || empty($interest_period)) {
            transaction_update($update_data, $transaction_id);

            continue;
        }

        switch ($user_group_permissions['newpoints_bank_system_interest_period_type']) {
            case INTEREST_PERIOD_TYPE_DAY:
                $interest_period_time = TIME_NOW - (int)(86400 * $interest_period);
                break;
            default:
                $interest_period_time = TIME_NOW - (int)(86400 * 7 * $interest_period);
                break;
        }

        $interest_points = $transaction_points * $interest_rate / 100;

        $interest_limit = (float)$user_group_permissions['newpoints_bank_system_interest_limit'];

        if (!empty($interest_limit) && $interest_points > $interest_limit) {
            $interest_points = $interest_limit;
        }

        if (!empty($interest_points) && $transaction_data['investment_stamp'] < $interest_period_time) {
            $user_data = get_user($user_id);

            points_add_simple($user_id, $interest_points);

            log_add(
                'bank_system_interest_profit',
                '',
                $user_data['username'] ?? '',
                $user_id,
                $interest_points,
                $transaction_id,
                0,
                0,
                LOGGING_TYPE_INCOME
            );

            $update_data['investment_stamp'] = TIME_NOW;

            if ((int)$transaction_data['investment_type'] !== TRANSACTION_INVESTMENT_TYPE_RECURRING) {
                $update_data['transaction_status'] = $transaction_status_cancelled;

                $update_data['complete_status'] = $complete_status_new;
            }
        }

        transaction_update($update_data, $transaction_id);

        user_rebuild_bank_details($user_id);
    }

    $where_clauses['complete_status'] = "complete_status IN ('{$complete_status_new}', '{$complete_status_processed}')";

    $where_clauses['transaction_status'] = "transaction_status='{$transaction_status_cancelled}'";

    $transaction_objects = transaction_get_multiple($where_clauses, [
        'transaction_id',
        'user_id',
        'transaction_points',
        'complete_status',
    ], ['order_by' => 'transaction_stamp', 'asc' => 'desc', 'limit' => 10]);

    foreach ($transaction_objects as $transaction_data) {
        $transaction_id = (int)$transaction_data['transaction_id'];

        $user_id = (int)$transaction_data['user_id'];

        $user_data = get_user($user_id);

        $complete_status = (int)$transaction_data['complete_status'];

        $transaction_points = (float)$transaction_data['transaction_points'];

        if ($complete_status === $complete_status_new) {
            points_add_simple($user_id, $transaction_points);

            transaction_update(['complete_status' => $complete_status_processed], $transaction_id);

            $complete_status = $complete_status_processed;
        }

        if ($complete_status === $complete_status_processed) {
            log_add(
                'bank_system_investment_cancel',
                '',
                $user_data['username'] ?? '',
                $user_id,
                $transaction_points,
                $transaction_id,
                0,
                0,
                LOGGING_TYPE_INCOME
            );

            transaction_update(['complete_status' => TRANSACTION_COMPLETE_STATUS_LOGGED], $transaction_id);
        }

        user_rebuild_bank_details($user_id);
    }

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

function user_update(int $user_id, array $update_data): int
{
    global $db;

    return (int)$db->update_query(
        'users',
        $update_data,
        "uid='{$user_id}'",
        1
    );
}

function user_rebuild_bank_details(int $user_id): bool
{
    global $db;

    $transaction_type_deposit = TRANSACTION_TYPE_DEPOSIT;

    $transaction_type_investment = TRANSACTION_TYPE_INVESTMENT;

    $transaction_type_withdraw = TRANSACTION_TYPE_WITHDRAW;

    $transaction_status_live = TRANSACTION_STATUS_LIVE;

    $complete_status_logged = TRANSACTION_COMPLETE_STATUS_LOGGED;

    $where_clauses = [
        "user_id='{$user_id}'",
        "transaction_status='{$transaction_status_live}'",
        "complete_status='{$complete_status_logged}'",
        'transaction_type' => "transaction_type='{$transaction_type_deposit}'"
    ];

    $transaction_objects = transaction_get_multiple(
        $where_clauses,
        ['SUM(transaction_points) AS total_deposit_points']
    );

    $total_deposit_points = (float)($transaction_objects[0]['total_deposit_points'] ?? 0);

    $where_clauses['transaction_type'] = "transaction_type='{$transaction_type_investment}'";

    $transaction_objects = transaction_get_multiple(
        $where_clauses,
        ['SUM(transaction_points) AS total_investment_points']
    );

    $total_investment_points = (float)($transaction_objects[0]['total_investment_points'] ?? 0);

    $where_clauses['transaction_type'] = "transaction_type='{$transaction_type_withdraw}'";

    $transaction_objects = transaction_get_multiple(
        $where_clauses,
        ['SUM(transaction_points) AS total_withdraw_points']
    );

    $total_withdraw_points = (float)($transaction_objects[0]['total_withdraw_points'] ?? 0);


    user_update($user_id, [
        'newpoints_bank' => $total_deposit_points - $total_withdraw_points,
        'newpoints_bank_investment' => $total_investment_points
    ]);

    return true;
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

function can_create_investment(int $user_id): bool
{
    $user_group_permissions = users_get_group_permissions($user_id);

    return !empty($user_group_permissions['newpoints_bank_system_can_invest']);
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

    if (isset($transaction_data['transaction_fee'])) {
        $insert_data['transaction_fee'] = (float)$transaction_data['transaction_fee'];
    }

    if (isset($transaction_data['transaction_stamp'])) {
        $insert_data['transaction_stamp'] = (int)$transaction_data['transaction_stamp'];
    } else {
        $insert_data['transaction_stamp'] = TIME_NOW;
    }

    if (isset($transaction_data['investment_type'])) {
        $insert_data['investment_type'] = (int)$transaction_data['investment_type'];
    }

    if (isset($transaction_data['investment_stamp'])) {
        $insert_data['investment_stamp'] = (int)$transaction_data['investment_stamp'];
    }

    if (isset($transaction_data['investment_execution_stamp'])) {
        $insert_data['investment_execution_stamp'] = (int)$transaction_data['investment_execution_stamp'];
    }

    if (isset($transaction_data['transaction_status'])) {
        $insert_data['transaction_status'] = (int)$transaction_data['transaction_status'];
    }

    if (isset($transaction_data['complete_status'])) {
        $insert_data['complete_status'] = (int)$transaction_data['complete_status'];
    }

    if ($is_update) {
        $db->update_query(
            'newpoints_bank_system_transactions',
            $transaction_data,
            "transaction_id='{$transaction_id}'"
        );

        return $transaction_id;
    } else {
        return (int)$db->insert_query('newpoints_bank_system_transactions', $insert_data);
    }
}

function transaction_update(array $transaction_data, int $transaction_id): int
{
    return transaction_insert($transaction_data, true, $transaction_id);
}

function transaction_get(array $where_clauses, array $query_fields = []): array
{
    global $db;

    $query = $db->simple_select(
        'newpoints_bank_system_transactions',
        implode(',', array_merge(['transaction_id'], $query_fields)),
        implode(' AND ', $where_clauses),
        ['limit' => 1]
    );

    return (array)$db->fetch_array($query);
}

function transaction_get_multiple(array $where_clauses, array $query_fields = [], array $query_options = []): array
{
    global $db;

    $query = $db->simple_select(
        'newpoints_bank_system_transactions',
        implode(',', $query_fields),
        implode(' AND ', $where_clauses),
        $query_options
    );

    if (!$db->num_rows($query)) {
        return [];
    }

    $transactions = [];

    while ($transaction_data = $db->fetch_array($query)) {
        $transactions[] = $transaction_data;
    }

    return $transactions;
}