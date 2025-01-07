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

use MyBB;

use function Newpoints\BankSystem\Core\can_create_transaction;
use function Newpoints\BankSystem\Core\can_manage;
use function Newpoints\BankSystem\Core\execute_task;
use function Newpoints\BankSystem\Core\transaction_insert;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\BankSystem\Core\templates_get;
use function Newpoints\Core\log_add;
use function Newpoints\Core\page_build_cancel_confirmation;
use function Newpoints\Core\page_build_purchase_confirmation;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\url_handler_build;

use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\BankSystem\Core\INTEREST_PERIOD_TYPE_DAY;
use const Newpoints\BankSystem\Core\TRANSACTION_TYPE_DEPOSIT;
use const Newpoints\BankSystem\Core\TRANSACTION_TYPE_WITHDRAW;

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
            'lang_string' => 'newpoints_bank_system_menu',
            'category' => 'user',
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
        ['order_by' => 'transaction_stamp', 'order_dir' => 'desc', 'limit' => $limit]
    );

    if (!$db->num_rows($query)) {
        return false;
    }

    $alternative_background = alt_trow(true);

    $transactions_list = '';

    while ($transaction_data = $db->fetch_array($query)) {
        $transaction_id = my_number_format($transaction_data['transaction_id']);

        if ((int)$transaction_data['transaction_type'] === TRANSACTION_TYPE_DEPOSIT) {
            $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_deposit;

            $transaction_type_class = 'transaction_type_deposit';
        } else {
            $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_withdrawal;

            $transaction_type_class = 'transaction_type_withdrawal';
        }

        $transaction_points = points_format((float)$transaction_data['transaction_points']);

        $transaction_stamp = my_date('normal', $transaction_data['transaction_stamp']);

        $transactions_list .= eval(templates_get('home_table_transactions_row'));

        $alternative_background = alt_trow();
    }

    $latest_transactions[] = eval(templates_get('home_table_transactions'));

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

function newpoints_terminate(): bool
{
    global $mybb, $action_name;

    if ($mybb->get_input('action') !== get_setting('bank_system_action_name')) {
        return false;
    }

    $action_name = get_setting('bank_system_action_name');

    if (empty($mybb->usergroup['newpoints_bank_system_can_view'])) {
        error_no_permission();
    }

    global $db, $lang, $theme, $header, $templates, $headerinclude, $footer;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_pagination, $newpoints_additional;

    $url_params = ['action' => $action_name];

    $is_manage_page = false;

    if ($mybb->get_input('manage', MyBB::INPUT_INT)) {
        $url_params['manage'] = 1;

        $is_manage_page = true;
    }

    add_breadcrumb(
        $lang->newpoints_bank_system_page_breadcrumb,
        $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
    );

    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);

    if ($current_page < 1) {
        $current_page = 1;
    }

    $per_page = get_setting('bank_system_per_page');

    if ($per_page < 1) {
        $per_page = 10;
    }

    $current_user_id = (int)$mybb->user['uid'];

    $newpoints_pagination = $newpoints_buttons = '';

    $order_id = $mybb->get_input('order_id', MyBB::INPUT_INT);

    if ($mybb->get_input('view') === 'transaction') {
        if (!can_create_transaction($current_user_id)) {
            error_no_permission();
        }

        $transaction_type_deposit = TRANSACTION_TYPE_DEPOSIT;

        $transaction_type_withdraw = TRANSACTION_TYPE_WITHDRAW;

        $transaction_type = $mybb->get_input('transaction_type', MyBB::INPUT_INT);

        $transaction_points = $mybb->get_input('transaction_points', MyBB::INPUT_FLOAT);

        $minimum_transaction_points = min(
            (float)$mybb->usergroup['newpoints_bank_system_minimum_deposit'],
            (float)$mybb->usergroup['newpoints_bank_system_minimum_withdraw']
        );

        if ($transaction_points < $minimum_transaction_points) {
            $transaction_points = $minimum_transaction_points;
        }

        $optionDisabledElementDeposit = $optionDisabledElementWithdraw = '';

        if (empty($mybb->usergroup['newpoints_bank_system_can_deposit'])) {
            $optionDisabledElementDeposit = 'disabled="disabled"';
        }

        if (empty($mybb->usergroup['newpoints_bank_system_can_withdraw'])) {
            $optionDisabledElementWithdraw = 'disabled="disabled"';
        }

        if ($mybb->request_method === 'post') {
            // we set this to false to force redirect the user to the confirm page
            $confirm_transaction = $mybb->get_input('confirm', MyBB::INPUT_BOOL);

            $insert_data = [
                'user_id' => $current_user_id,
                'transaction_type' => $transaction_type,
                'transaction_points' => $transaction_points,
            ];

            $errors = [];

            if ($insert_data['transaction_type'] === TRANSACTION_TYPE_DEPOSIT) {
                if (empty($mybb->usergroup['newpoints_bank_system_can_deposit'])) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_permission_deposit;
                }

                if ($insert_data['transaction_points'] < $mybb->usergroup['newpoints_bank_system_minimum_deposit']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_minimum_points_deposit;
                }

                if ($insert_data['transaction_points'] > $mybb->user['newpoints']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_enough_points_deposit;
                }
            } else {
                if (empty($mybb->usergroup['newpoints_bank_system_can_withdraw'])) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_permission_deposit;
                }

                if ($insert_data['transaction_points'] < $mybb->usergroup['newpoints_bank_system_minimum_withdraw']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_minimum_points_withdraw;
                }

                if ($insert_data['transaction_points'] > $mybb->user['newpoints_bank']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_enough_points_withdraw;
                }
            }

            $confirm_transaction = !empty($errors) && $confirm_transaction;

            if ($errors) {
                $newpoints_errors = inline_error($errors);
            }

            $mybb->settings['redirects'] = $mybb->user['showredirect'] = 0;

            if ($confirm_transaction) {
                transaction_insert($insert_data);
            } else {
                $url_params['view'] = 'transaction';

                $url_params['transaction_type'] = $transaction_type;

                $url_params['transaction_points'] = $transaction_points;

                add_breadcrumb(
                    $lang->newpoints_bank_system_page_breadcrumb_transaction,
                    $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
                );

                $lang->newpoints_page_confirm_table_purchase_title = $lang->newpoints_bank_system_page_confirm_transaction_title;

                $lang->newpoints_page_confirm_table_purchase_button = $lang->newpoints_bank_system_page_buttons_transaction_create;

                page_build_purchase_confirmation(
                    $lang->newpoints_bank_system_page_confirm_transaction_description,
                    'transaction_type',
                    $transaction_type,
                    'transaction',
                    eval(templates_get('page_transaction_confirm'))
                );
            }

            execute_task();

            redirect(
                $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
            );
        }

        global $theme;

        echo eval(templates_get('page_transaction', false));

        exit;
    } else {
        $query = $db->simple_select(
            'newpoints_bank_system_transactions',
            'COUNT(transaction_id) as total_transactions',
            "user_id='{$current_user_id}'"
        );

        $total_transactions = (int)$db->fetch_field($query, 'total_transactions');

        if ($current_page < 1) {
            $current_page = 1;
        }

        $limit_start = ($current_page - 1) * $per_page;

        $pages = ceil($total_transactions / $per_page);

        if ($current_page > $pages) {
            $limit_start = 0;

            $current_page = 1;
        }

        $query = $db->simple_select(
            'newpoints_bank_system_transactions',
            'transaction_id, transaction_type, transaction_points, transaction_stamp, transaction_status',
            "user_id='{$current_user_id}'",
            [
                'order_by' => 'transaction_stamp',
                'order_dir' => 'desc',
                'limit_start' => $limit_start,
                'limit' => $per_page
            ]
        );

        $transactions_list = '';

        if (!$db->num_rows($query)) {
            $transactions_list = eval(templates_get('page_table_transactions_empty'));
        } else {
            $alternative_background = alt_trow(true);

            while ($transaction_data = $db->fetch_array($query)) {
                $transaction_id = my_number_format($transaction_data['transaction_id']);

                if ((int)$transaction_data['transaction_type'] === TRANSACTION_TYPE_DEPOSIT) {
                    $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_deposit;

                    $transaction_type_class = 'transaction_type_deposit';
                } else {
                    $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_withdrawal;

                    $transaction_type_class = 'transaction_type_withdrawal';
                }

                $transaction_points = points_format((float)$transaction_data['transaction_points']);

                $transaction_stamp = my_date('normal', $transaction_data['transaction_stamp']);

                $transactions_list .= eval(templates_get('page_table_transactions_row'));

                $alternative_background = alt_trow();
            }

            if ($total_transactions > $per_page) {
                $newpoints_pagination = multipage(
                    $total_transactions,
                    $per_page,
                    $current_page,
                    url_handler_build($url_params)
                );

                if ($newpoints_pagination) {
                    $newpoints_pagination = eval(\Newpoints\Core\templates_get('page_pagination'));
                }
            }
        }

        if (!$is_manage_page && can_create_transaction($current_user_id)) {
            $transaction_url = url_handler_build(array_merge($url_params, ['view' => 'transaction']));

            $newpoints_buttons .= eval(templates_get('page_button_transaction'));
        }

        $page_interest_note = '';

        if (!empty($mybb->usergroup['newpoints_rate_bank_system_interest'])) {
            $user_interest_rate = my_number_format($mybb->usergroup['newpoints_rate_bank_system_interest'] / 100);

            $user_interest_period = my_number_format($mybb->usergroup['newpoints_rate_bank_system_interest_period']);

            switch ($mybb->usergroup['newpoints_rate_bank_system_interest_period_type']) {
                case INTEREST_PERIOD_TYPE_DAY:
                    if ($mybb->usergroup['newpoints_rate_bank_system_interest_period'] > 1) {
                        $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_days;
                    } else {
                        $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_day;
                    }
                    break;
                default:
                    if ($mybb->usergroup['newpoints_rate_bank_system_interest_period'] > 1) {
                        $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_weeks;
                    } else {
                        $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_week;
                    }
                    break;
            }

            $user_interest_limit = (float)$mybb->usergroup['newpoints_rate_bank_system_interest_limit'];

            if (!empty($user_interest_limit)) {
                $user_interest_limit = points_format($user_interest_limit);
            } else {
                $user_interest_limit = $lang->newpoints_bank_system_page_user_interest_limit_no_limit;
            }

            $page_interest_note = eval(templates_get('page_interest_note'));
        }

        $newpoints_content = eval(templates_get('page_table_transactions'));
    }

    if (can_manage() && !$is_manage_page && !$mybb->get_input('view')) {
        $manage_url = url_handler_build(array_merge($url_params, ['manage' => 1]));

        $newpoints_buttons .= eval(\Newpoints\Core\templates_get('button_manage'));
    }

    $page_title = $lang->newpoints_bank_system_page_title;

    $page_contents = eval(\Newpoints\Core\templates_get('page'));

    output_page($page_contents);

    exit;
}