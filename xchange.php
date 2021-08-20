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
 * Mollie webhook
 *
 * File         xchange.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
require_once('./../../../config.php');
require_once($CFG->dirroot . '/payment/gateway/mollie/thirdparty/Mollie/vendor/autoload.php');

use paygw_mollie\mollie_helper;

$params = [
    'component' => required_param('component', PARAM_COMPONENT),
    'paymentarea' => required_param('paymentarea', PARAM_AREA),
    'itemid' => required_param('itemid', PARAM_INT),
    'internalid' => required_param('internalid', PARAM_INT),
    'mollieid' => required_param('id', PARAM_RAW),
];

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/payment/gateway/mollie/xchange.php');
$PAGE->set_pagelayout('admin');
$pagetitle = 'MOLLIE WEBHOOK';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

// Instant, we want this AS QUICK as we can.
try {
    // Callback is provided with internal record ID to match OUR record.
    $transactionrecord = $DB->get_record('paygw_mollie', ['id' => $params['internalid'],
        'orderid' => $params['mollieid']], '*', MUST_EXIST);
    // Verify record.
    mollie_helper::assert_payment_record_variables($molliepaymentrecord,
            $params['component'], $params['paymentarea'], $params['itemid']);
    // And sycnhronize status.
    mollie_helper::synchronize_status(null, $transactionrecord);
    header("HTTP/1.1 200 OK");
} catch (\Exception $e) {
    // NON HTTP-200.
    header("HTTP/1.1 406 Not Acceptable");
}
exit;
