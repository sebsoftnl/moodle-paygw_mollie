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
 * @copyright   2021 RvD
 * @author      RvD <helpdesk@sebsoft.nl>
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
    'internalid' => required_param('internalid', PARAM_INT),
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
    if ($molliepaymentrecord->userid != $USER->id) {
        throw new moodle_exception('err:return:useridcheck', 'paygw_mollie');
    }
    $redirecturl = mollie_helper::determine_redirect_url($molliepaymentrecord->component,
            $molliepaymentrecord->paymentarea, $molliepaymentrecord->itemid);
    // Verify record.
    mollie_helper::assert_payment_record_variables($molliepaymentrecord,
            $params['component'], $params['paymentarea'], $params['itemid']);

    // Canceled, failed and expired are final. We'll display instead of doing "background processing".
    $externaltransaction = mollie_helper::get_mollie_payment($molliepaymentrecord);
    $break = [
        PaymentStatus::STATUS_CANCELED,
        PaymentStatus::STATUS_FAILED,
        PaymentStatus::STATUS_EXPIRED,
    ];
} catch (\dml_missing_record_exception $dme) {
    redirect(new moodle_url('/'), get_string('transactionrecordnotfound', 'paygw_mollie'), 0, notification::NOTIFY_ERROR);
} catch (moodle_exception $me) {
    redirect(new moodle_url('/'), $me->getMessage(), 0, notification::NOTIFY_ERROR);
} catch (\Exception $e) {
    echo $e->getMessage();
    redirect(new moodle_url('/'), get_string('unknownerror', 'paygw_mollie'), 0, notification::NOTIFY_ERROR);
}

// When we get here, we'll have an overlay and perform background processing/checking.
$longwait = 60;
$checkinterval = 5;
$pid = $molliepaymentrecord->id;
$PAGE->requires->js_call_amd('paygw_mollie/paymentstatus', 'init', [$pid, $checkinterval, $longwait]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('paymentstatus:heading', 'paygw_mollie'), 2);
echo '<div class="paygw_mollie_paymentstatus text-center">';
echo '<div class="alert alert-info">'.get_string('paymentstatus:explanation', 'paygw_mollie').'</div>';
echo '<div class="loader icon-size-6">';
echo $OUTPUT->render_from_template('core/loading', []);
echo '</div>';
echo '<div id="paygw_mollie_paymentstatus"></div>';
echo '<div id="paygw_redirect" class="hidden">';
echo '<div class="longwaitnotify alert alert-warning hidden">';
echo get_string('paymentstatus:longwaitnotify', 'paygw_mollie', format_time($longwait));
echo '</div>';
echo $OUTPUT->single_button($redirecturl, get_string('continue'), 'get', ['id' => 'paygw_mollie_continuebutton']);
echo '</div>';
echo '</div>';
echo $OUTPUT->footer();
