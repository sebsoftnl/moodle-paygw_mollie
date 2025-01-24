<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for the Mollie payment gateway
 *
 * File         settings.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading('paygw_mollie_settings', '', get_string('pluginname_desc', 'paygw_mollie')));

    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_mollie');

    $settings->add(new admin_setting_configcheckbox(
        'paygw_mollie/useinternalzeropayments',
        get_string('setting:useinternalzeropayments', 'paygw_mollie'),
        get_string('setting:useinternalzeropayments_help', 'paygw_mollie'),
        1
    ));

}
