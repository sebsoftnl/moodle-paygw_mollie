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
 * Mollie return page.
 *
 * File         return.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_mollie\mollie_helper;
use Mollie\Api\Types\PaymentStatus;
use core\output\notification;

require_once('./../../../config.php');
require_once($CFG->dirroot . '/payment/gateway/mollie/thirdparty/Mollie/vendor/autoload.php');

$params = [
    'component' => required_param('component', PARAM_COMPONENT),
    'paymentarea' => required_param('paymentarea', PARAM_AREA),
    'itemid' => required_param('itemid', PARAM_INT),
    'internalid' => required_param('internalid', PARAM_INT)
];

require_login();
$context = context_system::instance(); // Because we "have no scope".

$PAGE->set_context($context);
$PAGE->set_url('/payment/gateway/mollie/return.php', $params);
$PAGE->set_pagelayout('report');
$pagetitle = get_string('payment:returnpage', 'paygw_mollie');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

// Process status.
try {
    // Callback is provided with internal record ID to match OUR record.
    $molliepaymentrecord = $DB->get_record('paygw_mollie', ['id' => $params['internalid']], '*', MUST_EXIST);
    // Verify record.
    mollie_helper::assert_payment_record_variables($molliepaymentrecord,
            $params['component'], $params['paymentarea'], $params['itemid']);
    // Early exit: don't process if already paid. Might do more in the future?
    if ($molliepaymentrecord->status === PaymentStatus::STATUS_PAID) {
        // Already paid for!
        $url = mollie_helper::determine_redirect_url($molliepaymentrecord->component,
                $molliepaymentrecord->paymentarea, $molliepaymentrecord->itemid);
        redirect($url, get_string('paymentalreadypaid', 'paygw_mollie'), 0, 'success');
    }
    // Let the helper hit the floor!
    // This will do the validation and all that fun stuff.
    $externaltransaction = mollie_helper::synchronize_status(null, $molliepaymentrecord);
    if ($externaltransaction->status == PaymentStatus::STATUS_PAID) {
        // Deliver course is cared for by the helper!
        $url = mollie_helper::determine_redirect_url($molliepaymentrecord->component,
                $molliepaymentrecord->paymentarea, $molliepaymentrecord->itemid);
        redirect($url, get_string('paymentsuccessful', 'paygw_mollie'), 0, 'success');

    } else if ($externaltransaction->status == PaymentStatus::STATUS_PENDING) {
        // Display message.
        redirect(new moodle_url('/'), get_string('paymentpending', 'paygw_mollie'), 0, notification::NOTIFY_WARNING);
    } else if ($externaltransaction->status == PaymentStatus::STATUS_CANCELED) {
        // Back to main page with notification.
        redirect(new moodle_url('/'), get_string('paymentcancelled', 'paygw_mollie'), 0, notification::NOTIFY_WARNING);
    } else {
        // Back to main page with notification.
        redirect(new moodle_url('/'), get_string('cannotprocessstatus', 'paygw_mollie'), 0, notification::NOTIFY_WARNING);
    }

} catch (\dml_missing_record_exception $dme) {
    redirect(new moodle_url('/'), get_string('transactionrecordnotfound', 'paygw_mollie'), 0, notification::NOTIFY_ERROR);
} catch (moodle_exception $me) {
    redirect(new moodle_url('/'), $me->getMessage(), 0, notification::NOTIFY_ERROR);
} catch (\Exception $e) {
    echo $e->getMessage();
    redirect(new moodle_url('/'), get_string('unknownerror', 'paygw_mollie'), 0, notification::NOTIFY_ERROR);
}
