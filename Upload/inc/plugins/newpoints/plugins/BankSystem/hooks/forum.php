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

namespace NewPoints\BankSystem\Hooks\Forum;

use MyBB;

use function NewPoints\BankSystem\Core\can_create_investment;
use function NewPoints\BankSystem\Core\can_create_transaction;
use function NewPoints\BankSystem\Core\can_manage;
use function NewPoints\BankSystem\Core\execute_task;
use function NewPoints\BankSystem\Core\transaction_get;
use function NewPoints\BankSystem\Core\transaction_get_multiple;
use function NewPoints\BankSystem\Core\transaction_insert;
use function NewPoints\BankSystem\Core\transaction_update;
use function NewPoints\BankSystem\Core\templates_get;
use function NewPoints\Core\get_setting;
use function NewPoints\Core\language_load;
use function NewPoints\Core\main_file_name;
use function NewPoints\Core\page_build_cancel_confirmation;
use function NewPoints\Core\page_build_purchase_confirmation;
use function NewPoints\Core\points_format;
use function NewPoints\Core\url_handler_build;

use const NewPoints\BankSystem\Core\INTEREST_PERIOD_TYPE_DAY;
use const NewPoints\BankSystem\Core\TRANSACTION_COMPLETE_STATUS_LOGGED;
use const NewPoints\BankSystem\Core\TRANSACTION_COMPLETE_STATUS_NEW;
use const NewPoints\BankSystem\Core\TRANSACTION_COMPLETE_STATUS_PROCESSED;
use const NewPoints\BankSystem\Core\TRANSACTION_INVESTMENT_TYPE_NOT_RECURRING;
use const NewPoints\BankSystem\Core\TRANSACTION_INVESTMENT_TYPE_RECURRING;
use const NewPoints\BankSystem\Core\TRANSACTION_STATUS_LIVE;
use const NewPoints\BankSystem\Core\TRANSACTION_STATUS_CANCELLED;
use const NewPoints\BankSystem\Core\TRANSACTION_STATUS_NO_FUNDS;
use const NewPoints\BankSystem\Core\TRANSACTION_TYPE_DEPOSIT;
use const NewPoints\BankSystem\Core\TRANSACTION_TYPE_INVESTMENT;
use const NewPoints\BankSystem\Core\TRANSACTION_TYPE_WITHDRAW;
use const NewPoints\Core\DEBUG;

function newpoints_global_start(array &$hook_arguments): array
{
    if (DEBUG) {
        execute_task();
    }

    $hook_arguments['newpoints.php'] = array_merge($hook_arguments['newpoints.php'], [
        'newpoints_bank_system_page_table_transactions_row',
        'newpoints_bank_system_page_table_transactions_row_options_cancel',
        'newpoints_bank_system_page_button_transaction',
        'newpoints_bank_system_page_button_investment',
        'newpoints_bank_system_page_table_transactions',

        'newpoints_bank_system_home_user_details',
        'newpoints_bank_system_home_table_transactions_row',
        'newpoints_bank_system_home_table_transactions',

        'newpoints_bank_system_css',

        'newpoints_logs_transaction_type',
    ]);

    execute_task();

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

function newpoints_begin(): bool
{
    global $footer;

    $footer .= eval(templates_get('css'));

    return true;
}

function newpoints_home_end(): bool
{
    global $mybb;
    global $newpoints_bank_details;

    $newpoints_bank_details = '';

    if (!empty($mybb->usergroup['newpoints_bank_system_can_invest'])) {
        global $lang;

        language_load('bank_system');

        $user_interest_rate = my_number_format(($mybb->usergroup['newpoints_rate_bank_system_interest']) / 100);

        $user_interest_period = my_number_format($mybb->usergroup['newpoints_bank_system_interest_period']);

        switch ($mybb->usergroup['newpoints_bank_system_interest_period_type']) {
            case INTEREST_PERIOD_TYPE_DAY:
                if ($mybb->usergroup['newpoints_bank_system_interest_period'] > 1) {
                    $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_days;
                } else {
                    $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_day;
                }
                break;
            default:
                if ($mybb->usergroup['newpoints_bank_system_interest_period'] > 1) {
                    $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_weeks;
                } else {
                    $user_interest_period_type = $lang->newpoints_bank_system_page_user_interest_period_week;
                }
                break;
        }

        $user_interest_limit = (float)$mybb->usergroup['newpoints_bank_system_interest_limit'];

        if (!empty($user_interest_limit)) {
            $user_interest_limit = points_format($user_interest_limit);
        } else {
            $user_interest_limit = $lang->newpoints_bank_system_page_user_interest_limit_no_limit;
        }

        $newpoints_bank_details = eval(templates_get('home_user_details'));
    }

    if (!($limit = (int)get_setting('bank_system_home_transactions'))) {
        return false;
    }

    global $mybb, $lang, $theme;
    global $latest_transactions;

    language_load('bank_system');

    $current_user_id = (int)$mybb->user['uid'];

    $where_clauses = [
        "user_id='{$current_user_id}'"
    ];

    $transaction_objects = transaction_get_multiple($where_clauses, [
        'transaction_id',
        'transaction_type',
        'transaction_points',
        'transaction_stamp',
        'complete_status'
    ], ['order_by' => 'transaction_id', 'order_dir' => 'desc', 'limit' => $limit]);

    if (empty($transaction_objects)) {
        return false;
    }

    $alternative_background = alt_trow(true);

    $transactions_list = '';

    foreach ($transaction_objects as $transaction_data) {
        $transaction_id = my_number_format($transaction_data['transaction_id']);

        $transaction_type = (int)$transaction_data['transaction_type'];

        if ($transaction_type === TRANSACTION_TYPE_DEPOSIT) {
            $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_deposit;

            $transaction_type_class = 'transaction_type_deposit';
        } elseif ($transaction_type === TRANSACTION_TYPE_INVESTMENT) {
            $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_investment;

            $transaction_type_class = 'transaction_type_investment';
        } elseif ($transaction_type === TRANSACTION_TYPE_WITHDRAW) {
            $transaction_type = $lang->newpoints_bank_system_page_logs_table_type_withdraw;

            $transaction_type_class = 'transaction_type_withdraw';
        }

        $transaction_points = points_format((float)$transaction_data['transaction_points']);

        $transaction_stamp = my_date('normal', $transaction_data['transaction_stamp']);

        switch ($transaction_data['complete_status']) {
            case TRANSACTION_COMPLETE_STATUS_NEW:
                $transaction_complete_status = $lang->newpoints_bank_system_page_logs_table_complete_status_new;

                $transaction_complete_status_class = 'transaction_complete_status_new';
                break;
            case TRANSACTION_COMPLETE_STATUS_PROCESSED:
                $transaction_complete_status = $lang->newpoints_bank_system_page_logs_table_complete_status_processed;

                $transaction_complete_status_class = 'transaction_complete_status_processed';
                break;
            case TRANSACTION_COMPLETE_STATUS_LOGGED:
                $transaction_complete_status = $lang->newpoints_bank_system_page_logs_table_complete_status_logged;

                $transaction_complete_status_class = 'transaction_complete_status_logged';
                break;
        }

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
        'bank_system_deposit',
        'bank_system_deposit_fee',
        'bank_system_investment',
        'bank_system_investment_fee',
        'bank_system_withdraw',
        'bank_system_withdraw_fee',
        'bank_system_interest_profit',
        'bank_system_investment_cancel',
    ])) {
        return false;
    }

    global $lang;
    global $log_action, $log_primary, $log_secondary, $log_tertiary;

    language_load('bank_system');

    if ($log_data['action'] === 'bank_system_deposit') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_deposit;
    }

    if ($log_data['action'] === 'bank_system_deposit_fee') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_deposit_fee;
    }

    if ($log_data['action'] === 'bank_system_investment') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_investment;
    }

    if ($log_data['action'] === 'bank_system_investment_fee') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_investment_fee;
    }

    if ($log_data['action'] === 'bank_system_withdraw') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_withdraw;
    }

    if ($log_data['action'] === 'bank_system_withdraw_fee') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_withdraw_fee;
    }

    if ($log_data['action'] === 'bank_system_interest_profit') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_interest_profit;
    }

    if ($log_data['action'] === 'bank_system_investment_cancel') {
        $log_action = $lang->newpoints_signature_market_page_logs_bank_system_investment_cancel;
    }

    $transaction_id = (int)$log_data['log_primary_id'];

    $transaction_data = transaction_get(["transaction_id='{$transaction_id}'"], ['transaction_type']);

    if (empty($transaction_data)) {
        return false;
    }

    $transaction_id = (int)$transaction_data['transaction_type'];

    $log_primary = $lang->sprintf(
        $lang->newpoints_signature_market_page_logs_bank_system_log_type_transaction_id,
        $transaction_id
    );

    return true;
}

function newpoints_logs_end(): bool
{
    global $lang;
    global $action_types;

    language_load('bank_system');

    foreach ($action_types as $key => &$action_type) {
        if ($key === 'bank_system_deposit') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_deposit;
        }

        if ($key === 'bank_system_deposit_fee') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_deposit_fee;
        }

        if ($key === 'bank_system_investment') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_investment;
        }

        if ($key === 'bank_system_investment_fee') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_investment_fee;
        }

        if ($key === 'bank_system_withdraw') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_withdraw;
        }

        if ($key === 'bank_system_withdraw_fee') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_withdraw_fee;
        }

        if ($key === 'bank_system_interest_profit') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_interest_profit;
        }

        if ($key === 'bank_system_investment_cancel') {
            $action_type = $lang->newpoints_signature_market_page_logs_bank_system_investment_cancel;
        }
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
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_pagination, $newpoints_additional, $newpoints_user_balance_formatted;

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

    $transaction_status_live = TRANSACTION_STATUS_LIVE;

    $transaction_status_cancelled = TRANSACTION_STATUS_CANCELLED;

    $transaction_status_no_funds = TRANSACTION_STATUS_NO_FUNDS;

    if ($mybb->get_input('view') === 'cancel') {
        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            $confirm_transaction = $mybb->get_input('confirm', MyBB::INPUT_BOOL);

            $transaction_id = $mybb->get_input('transaction_id', MyBB::INPUT_INT);

            $transaction_data = transaction_get([
                "transaction_id='{$transaction_id}'",
                "user_id='{$current_user_id}'",
                "transaction_status='{$transaction_status_live}'"
            ]);

            if (empty($transaction_data)) {
                error_no_permission();
            }

            $update_data = [
                'transaction_status' => $transaction_status_cancelled,
                'complete_status' => TRANSACTION_COMPLETE_STATUS_NEW
            ];

            if ($confirm_transaction) {
                transaction_update($update_data, $transaction_id);

                execute_task();

                $mybb->settings['redirects'] = $mybb->user['showredirect'] = 0;

                redirect(
                    $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
                );
            } else {
                add_breadcrumb(
                    $lang->newpoints_bank_system_page_breadcrumb_transaction,
                    $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
                );

                $lang->newpoints_page_confirm_table_purchase_title = $lang->newpoints_bank_system_page_confirm_transaction_title;

                $lang->newpoints_page_confirm_table_purchase_button = $lang->newpoints_bank_system_page_buttons_transaction;

                page_build_cancel_confirmation(
                    'transaction_id',
                    $transaction_id,
                    $lang->newpoints_bank_system_page_confirm_investment_cancel_description,
                    'cancel'
                );
            }
        }

        error_no_permission();
    } elseif ($mybb->get_input('view') === 'transaction') {
        if (!can_create_transaction($current_user_id)) {
            error_no_permission();
        }

        $transaction_type = $mybb->get_input('transaction_type', MyBB::INPUT_INT);

        $transaction_points = $mybb->get_input('transaction_points', MyBB::INPUT_FLOAT);

        $transaction_type_deposit = TRANSACTION_TYPE_DEPOSIT;

        $transaction_type_withdraw = TRANSACTION_TYPE_WITHDRAW;

        $minimum_transaction_points = min(
            (float)$mybb->usergroup['newpoints_bank_system_minimum_deposit'],
            (float)$mybb->usergroup['newpoints_bank_system_minimum_withdraw']
        );

        if ($transaction_points < $minimum_transaction_points) {
            $transaction_points = $minimum_transaction_points;
        }

        $optionDisabledSelectedElementDeposit = $optionDisabledSelectedElementWithdraw = '';

        if (empty($mybb->usergroup['newpoints_bank_system_can_deposit'])) {
            $optionDisabledSelectedElementDeposit = 'disabled="disabled"';
        } elseif ($transaction_type === $transaction_type_deposit) {
            $optionDisabledSelectedElementDeposit = 'selected="selected"';
        }

        if (empty($mybb->usergroup['newpoints_bank_system_can_withdraw'])) {
            $optionDisabledSelectedElementWithdraw = 'disabled="disabled"';
        } elseif ($transaction_type === $transaction_type_withdraw) {
            $optionDisabledSelectedElementWithdraw = 'selected="selected"';
        }

        $transaction_rate_deposit = (float)$mybb->usergroup['newpoints_rate_bank_system_deposit'];

        $transaction_rate_withdraw = (float)$mybb->usergroup['newpoints_rate_bank_system_withdraw'];

        $transaction_rate_text = $lang->sprintf(
            $lang->newpoints_bank_system_page_transaction_rate_deposit,
            my_number_format($transaction_rate_deposit)
        );

        $transaction_rate_deposit_text = eval(templates_get('page_transaction_rate'));

        $transaction_rate_text = $lang->sprintf(
            $lang->newpoints_bank_system_page_transaction_rate_withdraw,
            my_number_format($transaction_rate_withdraw)
        );

        $transaction_rate_withdraw_text = eval(templates_get('page_transaction_rate'));

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            // we set this to false to force redirect the user to the confirm page
            $confirm_transaction = $mybb->get_input('confirm', MyBB::INPUT_BOOL);

            $insert_data = [
                'user_id' => $current_user_id,
                'transaction_type' => $transaction_type,
                'transaction_points' => $transaction_points,
                'transaction_fee' => 0
            ];

            $errors = [];

            if ($insert_data['transaction_type'] === TRANSACTION_TYPE_DEPOSIT) {
                if ($transaction_rate_deposit > 0) {
                    $insert_data['transaction_fee'] = $insert_data['transaction_points'] * ($transaction_rate_deposit / 100);
                }

                if (empty($mybb->usergroup['newpoints_bank_system_can_deposit'])) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_permission_deposit;
                }

                if ($insert_data['transaction_points'] < $mybb->usergroup['newpoints_bank_system_minimum_deposit']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_minimum_points_deposit;
                }

                if ($insert_data['transaction_points'] + $insert_data['transaction_fee'] > $mybb->user['newpoints']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_enough_points_deposit;
                }
            } else {
                if ($transaction_rate_withdraw > 0) {
                    $insert_data['transaction_fee'] = $insert_data['transaction_points'] * ($transaction_rate_withdraw / 100);
                }
                if (empty($mybb->usergroup['newpoints_bank_system_can_withdraw'])) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_permission_deposit;
                }

                if ($insert_data['transaction_points'] < $mybb->usergroup['newpoints_bank_system_minimum_withdraw']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_minimum_points_withdraw;
                }

                if ($insert_data['transaction_points'] > $mybb->user['newpoints_bank']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_enough_points_withdraw;
                }

                if ($insert_data['transaction_fee'] > $mybb->user['newpoints']) {
                    $errors[] = $lang->newpoints_bank_system_error_transaction_no_enough_points_withdraw_fee;
                }
            }

            $confirm_transaction = empty($errors) && $confirm_transaction;

            if ($errors) {
                $newpoints_errors = inline_error($errors);
            }

            if ($confirm_transaction) {
                transaction_insert($insert_data);
            } else {
                add_breadcrumb(
                    $lang->newpoints_bank_system_page_breadcrumb_transaction,
                    $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
                );

                $lang->newpoints_page_confirm_table_purchase_title = $lang->newpoints_bank_system_page_confirm_transaction_title;

                $lang->newpoints_page_confirm_table_purchase_button = $lang->newpoints_bank_system_page_buttons_transaction;

                page_build_purchase_confirmation(
                    $lang->newpoints_bank_system_page_confirm_transaction_description,
                    'transaction_type',
                    $transaction_type,
                    'transaction',
                    eval(templates_get('page_transaction_confirm'))
                );
            }

            execute_task();

            $mybb->settings['redirects'] = $mybb->user['showredirect'] = 0;

            redirect(
                $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
            );
        }

        global $theme;

        echo eval(templates_get('page_transaction', false));

        exit;
    } elseif ($mybb->get_input('view') === 'investment') {
        if (!can_create_investment($current_user_id)) {
            error_no_permission();
        }

        $transaction_type = TRANSACTION_TYPE_INVESTMENT;

        $investment_type = $mybb->get_input('investment_type', MyBB::INPUT_INT);

        $investment_type_not_recurring = TRANSACTION_INVESTMENT_TYPE_NOT_RECURRING;

        $investment_type_recurring = TRANSACTION_INVESTMENT_TYPE_RECURRING;

        $transaction_points = $mybb->get_input('transaction_points', MyBB::INPUT_FLOAT);

        $minimum_transaction_points = min(
            (float)$mybb->usergroup['newpoints_bank_system_minimum_deposit'],
            (float)$mybb->usergroup['newpoints_bank_system_minimum_withdraw']
        );

        if ($transaction_points < $minimum_transaction_points) {
            $transaction_points = $minimum_transaction_points;
        }

        $optionDisabledSelectedElementNotRecurring = $optionDisabledSelectedElementRecurring = '';

        if ($investment_type === $investment_type_recurring) {
            $optionDisabledSelectedElementRecurring = 'selected="selected"';
        } else {
            $optionDisabledSelectedElementNotRecurring = 'selected="selected"';

            $investment_type = $investment_type_not_recurring;
        }

        $transaction_rate_deposit = (float)$mybb->usergroup['newpoints_rate_bank_system_deposit'];

        $transaction_rate_text = $lang->sprintf(
            $lang->newpoints_bank_system_page_transaction_rate_deposit,
            my_number_format($transaction_rate_deposit)
        );

        $transaction_rate_deposit_text = eval(templates_get('page_transaction_rate'));

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            // we set this to false to force redirect the user to the confirm page
            $confirm_transaction = $mybb->get_input('confirm', MyBB::INPUT_BOOL);

            $insert_data = [
                'user_id' => $current_user_id,
                'transaction_type' => $transaction_type,
                'transaction_points' => $transaction_points,
                'investment_type' => $investment_type,
                'transaction_fee' => 0
            ];

            $errors = [];
            if ($transaction_rate_deposit > 0) {
                $insert_data['transaction_fee'] = $insert_data['transaction_points'] * ($transaction_rate_deposit / 100);
            }

            if ($insert_data['transaction_points'] < $mybb->usergroup['newpoints_bank_system_minimum_deposit']) {
                $errors[] = $lang->newpoints_bank_system_error_transaction_minimum_points_deposit;
            }

            if ($insert_data['transaction_points'] + $insert_data['transaction_fee'] > $mybb->user['newpoints']) {
                $errors[] = $lang->newpoints_bank_system_error_transaction_no_enough_points_investment;
            }

            $confirm_transaction = empty($errors) && $confirm_transaction;

            if ($errors) {
                $newpoints_errors = inline_error($errors);
            }

            if ($confirm_transaction) {
                transaction_insert($insert_data);
            } else {
                add_breadcrumb(
                    $lang->newpoints_bank_system_error_transaction_no_enough_points_investment,
                    $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
                );

                $lang->newpoints_page_confirm_table_purchase_title = $lang->newpoints_bank_system_page_confirm_investment_title;

                $lang->newpoints_page_confirm_table_purchase_button = $lang->newpoints_bank_system_page_buttons_investment;

                page_build_purchase_confirmation(
                    $lang->newpoints_bank_system_page_confirm_transaction_description,
                    'investment_type',
                    $investment_type,
                    'investment',
                    eval(templates_get('page_investment_confirm'))
                );
            }

            execute_task();

            $mybb->settings['redirects'] = $mybb->user['showredirect'] = 0;

            redirect(
                $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
            );
        }

        global $theme;

        echo eval(templates_get('page_investment', false));

        exit;
    } else {
        $transaction_objects = transaction_get_multiple(["user_id='{$current_user_id}'"], [
            'COUNT(transaction_id) as total_transactions'
        ]);

        $total_transactions = (int)($transaction_objects[0]['total_transactions'] ?? 0);

        if ($current_page < 1) {
            $current_page = 1;
        }

        $limit_start = ($current_page - 1) * $per_page;

        $pages = ceil($total_transactions / $per_page);

        if ($current_page > $pages) {
            $limit_start = 0;

            $current_page = 1;
        }
        $transaction_objects = transaction_get_multiple(["user_id='{$current_user_id}'"], [
            'transaction_id',
            'transaction_type',
            'transaction_points',
            'transaction_stamp',
            'investment_type',
            'investment_stamp',
            'investment_execution_stamp',
            'transaction_status',
            'complete_status'
        ], [
            'order_by' => 'transaction_id',
            'order_dir' => 'desc',
            'limit_start' => $limit_start,
            'limit' => $per_page
        ]);

        $transactions_list = '';

        if (empty($transaction_objects)) {
            $transactions_list = eval(templates_get('page_table_transactions_empty'));
        } else {
            $alternative_background = alt_trow(true);

            foreach ($transaction_objects as $transaction_data) {
                $transaction_id = my_number_format($transaction_data['transaction_id']);

                $transaction_type = (int)$transaction_data['transaction_type'];

                if ($transaction_type === TRANSACTION_TYPE_DEPOSIT) {
                    $transaction_type = $lang->newpoints_bank_system_page_table_transactions_type_deposit;

                    $transaction_type_class = 'transaction_type_deposit';
                } elseif ($transaction_type === TRANSACTION_TYPE_INVESTMENT) {
                    $transaction_type = $lang->newpoints_bank_system_page_table_transactions_type_investment;

                    $transaction_type_class = 'transaction_type_investment';
                } elseif ($transaction_type === TRANSACTION_TYPE_WITHDRAW) {
                    $transaction_type = $lang->newpoints_bank_system_page_table_transactions_type_withdraw;

                    $transaction_type_class = 'transaction_type_withdraw';
                }

                $transaction_points = points_format((float)$transaction_data['transaction_points']);

                $transaction_stamp = my_date('normal', $transaction_data['transaction_stamp']);

                $investment_type = $investment_stamp = $investment_execution_stamp = $transaction_options = '-';

                if ((int)$transaction_data['transaction_type'] === TRANSACTION_TYPE_INVESTMENT &&
                    (int)$transaction_data['transaction_status'] === $transaction_status_live) {
                    switch ($transaction_data['investment_type']) {
                        case TRANSACTION_INVESTMENT_TYPE_NOT_RECURRING:
                            $investment_type = $lang->newpoints_bank_system_page_table_transactions_investment_type_not_recurring;
                            break;
                        case TRANSACTION_INVESTMENT_TYPE_RECURRING:
                            $investment_type = $lang->newpoints_bank_system_page_table_transactions_investment_type_recurring;
                            break;
                    }

                    $investment_stamp = my_date('normal', $transaction_data['investment_stamp']);

                    $investment_execution_stamp = my_date('normal', $transaction_data['investment_execution_stamp']);

                    $transaction_options = eval(templates_get('page_table_transactions_row_options_cancel'));
                }

                switch ($transaction_data['transaction_status']) {
                    case $transaction_status_live:
                        $transaction_status = $lang->newpoints_bank_system_page_table_transactions_transaction_status_live;

                        $transaction_complete_status_class = 'transaction_complete_status_new';
                        break;
                    case $transaction_status_cancelled:
                        $transaction_status = $lang->newpoints_bank_system_page_table_transactions_transaction_status_cancelled;

                        $transaction_complete_status_class = 'transaction_complete_status_processed';
                        break;
                    case $transaction_status_no_funds:
                        $transaction_status = $lang->newpoints_bank_system_page_table_transactions_transaction_status_no_funds;

                        $transaction_complete_status_class = 'transaction_complete_status_logged';
                        break;
                }

                switch ($transaction_data['complete_status']) {
                    case TRANSACTION_COMPLETE_STATUS_NEW:
                        $transaction_complete_status = $lang->newpoints_bank_system_page_table_transactions_complete_status_new;

                        $transaction_complete_status_class = 'transaction_complete_status_new';
                        break;
                    case TRANSACTION_COMPLETE_STATUS_PROCESSED:
                        $transaction_complete_status = $lang->newpoints_bank_system_page_table_transactions_complete_status_processed;

                        $transaction_complete_status_class = 'transaction_complete_status_processed';
                        break;
                    case TRANSACTION_COMPLETE_STATUS_LOGGED:
                        $transaction_complete_status = $lang->newpoints_bank_system_page_table_transactions_complete_status_logged;

                        $transaction_complete_status_class = 'transaction_complete_status_logged';
                        break;
                }

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
                    $newpoints_pagination = eval(\NewPoints\Core\templates_get('page_pagination'));
                }
            }
        }

        if (!$is_manage_page && can_create_transaction($current_user_id)) {
            $transaction_url = url_handler_build(array_merge($url_params, ['view' => 'transaction']));

            $newpoints_buttons .= eval(templates_get('page_button_transaction'));
        }

        if (!$is_manage_page && can_create_investment($current_user_id)) {
            $investment_url = url_handler_build(array_merge($url_params, ['view' => 'investment']));

            $newpoints_buttons .= eval(templates_get('page_button_investment'));
        }

        $user_bank_balance = points_format((int)$mybb->user['newpoints_bank']);

        $user_bank_investment_balance = points_format((int)$mybb->user['newpoints_bank_investment']);

        $newpoints_content = eval(templates_get('page_table_transactions'));
    }

    if (can_manage() && !$is_manage_page && !$mybb->get_input('view')) {
        $manage_url = url_handler_build(array_merge($url_params, ['manage' => 1]));

        $newpoints_buttons .= eval(\NewPoints\Core\templates_get('button_manage'));
    }

    $page_title = $lang->newpoints_bank_system_page_title;

    $page_contents = eval(\NewPoints\Core\templates_get('page'));

    output_page($page_contents);

    exit;
}

function fetch_wol_activity_end(array &$hook_parameters): array
{
    if (my_strpos($hook_parameters['location'], main_file_name()) === false ||
        my_strpos($hook_parameters['location'], 'action=' . get_setting('bank_system_action_name')) === false) {
        return $hook_parameters;
    }

    $hook_parameters['activity'] = 'newpoints_bank_system';

    return $hook_parameters;
}

function build_friendly_wol_location_end(array $hook_parameters): array
{
    global $mybb, $lang;

    language_load('bank_system');

    switch ($hook_parameters['user_activity']['activity']) {
        case 'newpoints_bank_system':
            $hook_parameters['location_name'] = $lang->sprintf(
                $lang->newpoints_bank_system_wol_location,
                $mybb->settings['bburl'],
                url_handler_build(['action' => get_setting('bank_system_action_name')])
            );
            break;
    }

    return $hook_parameters;
}