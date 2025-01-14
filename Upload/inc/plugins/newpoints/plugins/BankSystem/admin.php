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

namespace Newpoints\BankSystem\Admin;

use function Newpoints\Admin\db_build_field_definition;
use function Newpoints\Admin\db_drop_columns;
use function Newpoints\Admin\db_verify_columns;
use function Newpoints\Admin\db_verify_columns_exists;
use function Newpoints\Admin\db_verify_tables;
use function Newpoints\Admin\db_verify_tables_exists;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_remove;
use function Newpoints\Core\plugins_version_delete;
use function Newpoints\Core\plugins_version_get;
use function Newpoints\Core\plugins_version_update;
use function Newpoints\Core\settings_remove;
use function Newpoints\Core\templates_remove;
use function Newpoints\Core\task_enable;
use function Newpoints\Core\task_disable;
use function Newpoints\Core\task_delete;

use const Newpoints\BankSystem\Core\INTEREST_PERIOD_TYPE_DAY;
use const Newpoints\BankSystem\Core\INTEREST_PERIOD_TYPE_WEEK;

const TABLES_DATA = [
    'newpoints_bank_system_transactions' => [
        'transaction_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'transaction_type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'transaction_points' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0
        ],
        'transaction_fee' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0
        ],
        'transaction_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'investment_type' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'investment_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'investment_execution_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'transaction_status' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 1
        ],
        'complete_status' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ]
        // todo: maybe approval system, but the withdraw points system already offers that
    ]
];

const FIELDS_DATA = [
    'users' => [
        'newpoints_bank' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                'step' => 0.01,
            ]
        ],
        'newpoints_bank_investment' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                'step' => 0.01,
            ]
        ],
    ],
    'usergroups' => [
        'newpoints_bank_system_can_view' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ],
        'newpoints_bank_system_can_deposit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ],
        'newpoints_bank_system_can_invest' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'checkBox'
        ],
        'newpoints_bank_system_can_withdraw' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ],
        'newpoints_bank_system_minimum_deposit' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 1,
            'formType' => 'numericField',
            'formOptions' => [
                'min' => 1,
                'step' => 0.01,
            ]
        ],
        'newpoints_bank_system_minimum_withdraw' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 1,
            'formType' => 'numericField',
            'formOptions' => [
                'min' => 1,
                'step' => 0.01,
            ]
        ],
        'newpoints_rate_bank_system_deposit' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                'max' => 100,
            ]
        ],
        'newpoints_rate_bank_system_withdraw' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                'max' => 100,
            ]
        ],
        'newpoints_rate_bank_system_interest' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                'max' => 100,
            ]
        ],
        'newpoints_bank_system_interest_period' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'numericField'
        ],
        'newpoints_bank_system_interest_period_type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'selectField',
            'formFunction' => '\Newpoints\BankSystem\Admin\options_list_interest_period_type'
        ],
        'newpoints_bank_system_interest_limit' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
    ]
];

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
        'version' => '3.1.0',
        'versioncode' => 3100,
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
        !$db->field_exists('newpoints_bank_system_withdraw', 'usergroups')) {
        $db->rename_column(
            'usergroups',
            'newpoints_bank_system_withdraw',
            'newpoints_rate_bank_system_withdraw',
            db_build_field_definition(FIELDS_DATA['usergroups']['newpoints_rate_bank_system_withdraw'])
        );
    }

    if ($db->field_exists('newpoints_bank_system_interest', 'usergroups') &&
        !$db->field_exists('newpoints_bank_system_interest', 'usergroups')) {
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