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
 * External functions and service definitions for the Mollie payment gateway plugin.
 *
 * File         services.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_mollie_get_methods' => [
        'classname'   => 'paygw_mollie\external',
        'methodname'  => 'get_methods',
        'classpath'   => '',
        'description' => 'Fetch payment methods',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_mollie_create_payment' => [
        'classname'   => 'paygw_mollie\external',
        'methodname'  => 'create_payment',
        'classpath'   => '',
        'description' => 'Create a payment',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
