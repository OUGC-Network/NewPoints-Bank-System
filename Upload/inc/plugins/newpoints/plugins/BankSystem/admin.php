<?php

/***************************************************************************
 *
 *    NewPoints Bank System plugin (/inc/plugins/BankSystem/admin.php)
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

namespace NewPoints\BankSystem\Admin;

use function NewPoints\Admin\db_build_field_definition;
use function NewPoints\Admin\db_drop_columns;
use function NewPoints\Admin\db_verify_columns;
use function NewPoints\Admin\db_verify_columns_exists;
use function NewPoints\Admin\db_verify_tables;
use function NewPoints\Admin\db_verify_tables_exists;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_remove;
use function NewPoints\Core\plugins_version_delete;
use function NewPoints\Core\plugins_version_get;
use function NewPoints\Core\plugins_version_update;
use function NewPoints\Core\settings_remove;
use function NewPoints\Core\templates_remove;
use function NewPoints\Core\task_enable;
use function NewPoints\Core\task_disable;
use function NewPoints\Core\task_delete;

use const NewPoints\BankSystem\Core\FIELDS_DATA;
use const NewPoints\BankSystem\Core\INTEREST_PERIOD_TYPE_DAY;
use const NewPoints\BankSystem\Core\INTEREST_PERIOD_TYPE_WEEK;
use const NewPoints\BankSystem\Core\TABLES_DATA;

function plugin_information(): array
{
    global $lang;

    language_load('bank_system');

    return [
        'name' => 'Bank System',
        'description' => $lang->newpoints_bank_system_desc,
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '3.1.2',
        'versioncode' => 3102,
        'compatibility' => '31*',
        'codename' => 'newpoints_bank_system'
    ];
}

function plugin_activation(): bool
{
    global $db, $lang;

    language_load('bank_system');

    task_enable(
        'newpoints_bank_system',
        $lang->newpoints_bank_system,
        $lang->newpoints_bank_system_desc
    );

    $current_version = plugins_version_get('bank_system');

    $new_version = (int)plugin_information()['versioncode'];

    /*~*~* RUN UPDATES START *~*~*/

    if ($db->field_exists('newpoints_bank_system_deposit', 'usergroups') &&
        !$db->field_exists('newpoints_rate_bank_system_deposit', 'usergroups')) {
        $db->rename_column(
            'usergroups',
            'newpoints_bank_system_deposit',
            'newpoints_rate_bank_system_deposit',
            db_build_field_definition(FIELDS_DATA['usergroups']['newpoints_rate_bank_system_deposit'])
        );
    }

    if ($db->field_exists('newpoints_bank_system_withdraw', 'usergroups') &&
        !$db->field_exists('newpoints_rate_bank_system_withdraw', 'usergroups')) {
        $db->rename_column(
            'usergroups',
            'newpoints_bank_system_withdraw',
            'newpoints_rate_bank_system_withdraw',
            db_build_field_definition(FIELDS_DATA['usergroups']['newpoints_rate_bank_system_withdraw'])
        );
    }

    if ($db->field_exists('newpoints_bank_system_interest', 'usergroups') &&
        !$db->field_exists('newpoints_rate_bank_system_interest', 'usergroups')) {
        $db->rename_column(
            'usergroups',
            'newpoints_bank_system_interest',
            'newpoints_rate_bank_system_interest',
            db_build_field_definition(FIELDS_DATA['usergroups']['newpoints_rate_bank_system_interest'])
        );
    }

    /*~*~* RUN UPDATES END *~*~*/

    db_verify_tables(TABLES_DATA);

    db_verify_columns(FIELDS_DATA);

    plugins_version_update('bank_system', $new_version);

    return true;
}

function plugin_deactivation(): bool
{
    task_disable('newpoints_bank_system');

    return true;
}

function plugin_is_installed(): bool
{
    return db_verify_tables_exists(TABLES_DATA) &&
        db_verify_columns_exists(TABLES_DATA) &&
        db_verify_columns_exists(FIELDS_DATA);
}

function plugin_uninstallation(): bool
{
    log_remove(
        [
            ''
        ]
    );

    db_drop_columns(FIELDS_DATA);

    settings_remove(
        [
            'action_name',
            'manage_groups',
            'per_page',
            'menu_order',
            'home_transactions'
        ],
        'newpoints_bank_system_'
    );

    templates_remove(['home_table_transactions', 'home_table_transactions_row'], 'newpoints_bank_system_');

    task_delete('newpoints_bank_system');

    plugins_version_delete('bank_system');

    return true;
}

function options_list_interest_period_type(): array
{
    global $lang;

    return [
        INTEREST_PERIOD_TYPE_DAY => $lang->days,
        INTEREST_PERIOD_TYPE_WEEK => $lang->weeks
    ];
}