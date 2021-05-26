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
 * Contains helper class to work with Mollie.
 *
 * File         mollie_helper.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_mollie;

use core_payment\helper;
use stdClass;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/payment/gateway/mollie/thirdparty/Mollie/vendor/autoload.php');

/**
 * Contains helper class to work with Mollie.
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mollie_helper {

    /**
     * Get DB instance
     *
     * @return \moodle_database
     */
    protected static function db() {
        global $DB;
        return $DB;
    }

    /**
     * Assert or throw
     *
     * @param bool $bool
     * @param string $errorcode
     * @param string $module
     * @throws moodle_exception
     */
    public static function assert(bool $bool, string $errorcode, string $module = 'moodle') {
        if (!$bool) {
            throw new moodle_exception($errorcode, $module);
        }
    }

    /**
     * Fetch Mollie Payment entity based on the payment ID.
     *
     * @param string $molliepaymentid
     * @return \Mollie\Api\Resources\Payment
     */
    public static function get_mollie_payment(string $molliepaymentid) {
        // Mollie payment record (stores status).
        $molliepaymentrecord = static::db()->get_record('paygw_mollie',
                ['mollie_orderid' => $molliepaymentid], '*', MUST_EXIST);
        // Config depends on the payment context.
        $config = helper::get_gateway_configuration($molliepaymentrecord->component,
                $molliepaymentrecord->paymentarea, $molliepaymentrecord->itemid, 'mollie');

        // Finally we can initiate the Mollie API.
        $mollie = new \Mollie\Api\MollieApiClient();
        // DO NOT USE config. Testmode was stored in the mollie payment record.
        if (!empty($molliepaymentrecord->testmode)) {
            $mollie->setApiKey($config['apikeytest']);
        } else {
            $mollie->setApiKey($config['apikey']);
        }
        $molliepayment = $mollie->payments->get($molliepaymentid);
        return $molliepayment;
    }

    /**
     * Try to synchronize the status for a payment based on the internal transactionrecord,
     * the Mollie Transaction info, or both.
     *
     * This method performs validation on whether the information from the external
     * source and internal transaction records match.
     *
     * @param \Mollie\Api\Resources\Payment|null $transaction
     * @param stdClass|null $transactionrecord
     * @return \Mollie\Api\Resources\Payment the Mollie transaction info.
     * @throws moodle_exception
     */
    public static function synchronize_status(\Mollie\Api\Resources\Payment $transaction = null,
            stdClass $transactionrecord = null) {
        global $DB;
        if ($transaction === null && $transactionrecord === null) {
            throw new moodle_exception('err:synchronizestatus:args:invalid', 'paygw_mollie');
        }

        if ($transaction === null) {
            $config = helper::get_gateway_configuration($transactionrecord->component,
                    $transactionrecord->paymentarea, $transactionrecord->itemid, 'mollie');

            // Finally we can initiate the Mollie API.
            $mollie = new \Mollie\Api\MollieApiClient();
            // DO NOT USE config. Testmode was stored in the mollie payment record.
            if (!empty($transactionrecord->testmode)) {
                $mollie->setApiKey($config['apikeytest']);
            } else {
                $mollie->setApiKey($config['apikey']);
            }
            $transaction = $mollie->payments->get($transactionrecord->orderid);
        }
        if ($transactionrecord === null) {
            $transactionrecord = $DB->get_record('paygw_mollie',
                    ['orderid' => $transaction->id], '*', MUST_EXIST);
        }
        list($ccomponent, $cpaymentarea, $citemid, $cuserid) = explode('|', $transaction->metadata->extra1);
        // Ok, validate.
        if ($transactionrecord->component !== $ccomponent) {
            throw new moodle_exception('err:validatetransaction:component', 'paygw_mollie');
        }
        if ($transactionrecord->paymentarea !== $cpaymentarea) {
            throw new moodle_exception('err:validatetransaction:paymentarea', 'paygw_mollie');
        }
        if ($transactionrecord->itemid !== $citemid) {
            throw new moodle_exception('err:validatetransaction:itemid', 'paygw_mollie');
        }
        if ($transactionrecord->userid !== $cuserid) {
            throw new moodle_exception('err:validatetransaction:userid', 'paygw_mollie');
        }

        // Do we need to do anything?
        $transactionstatus = $transaction->status;
        if ($transactionrecord->status == $transactionstatus) {
            // Status has not changed so break!
            return $transaction;
        }

        // Update state.
        $transactionrecord->status = $transactionstatus;
        $transactionrecord->timemodified = time();
        $DB->update_record('paygw_mollie', $transactionrecord);

        // Now finally, perform actual order delivery if paid.
        switch ($transactionstatus) {
            case \Mollie\Api\Types\PaymentStatus::STATUS_OPEN:
                break;
            case \Mollie\Api\Types\PaymentStatus::STATUS_CANCELED:
                break;
            case \Mollie\Api\Types\PaymentStatus::STATUS_EXPIRED:
                break;
            case \Mollie\Api\Types\PaymentStatus::STATUS_PENDING:
                break;
            case \Mollie\Api\Types\PaymentStatus::STATUS_FAILED:
                break;
            case \Mollie\Api\Types\PaymentStatus::STATUS_PAID:
                // Deliver course.
                $payable = helper::get_payable($transactionrecord->component,
                        $transactionrecord->paymentarea, $transactionrecord->itemid);
                $cost = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(),
                        helper::get_gateway_surcharge('mollie'));
                $paymentid = helper::save_payment($payable->get_account_id(),
                        $transactionrecord->component, $transactionrecord->paymentarea,
                        $transactionrecord->itemid, $transactionrecord->userid,
                        $cost, $payable->get_currency(), 'mollie');
                helper::deliver_order($transactionrecord->component, $transactionrecord->paymentarea,
                        $transactionrecord->itemid, $paymentid, $transactionrecord->userid);
                // Set payment ID!
                $transactionrecord->paymentid = $paymentid;
                $transactionrecord->timemodified = time();
                $DB->update_record('paygw_mollie', $transactionrecord);
                break;
        }

        return $transaction;
    }

    /**
     * Determine the redirect URL.
     *
     * @param string $component
     * @param string $paymentarea
     * @param string $itemid
     * @return moodle_url
     */
    public static function determine_redirect_url($component, $paymentarea, $itemid) {
        global $CFG;
        // Find redirection.
        $url = new moodle_url('/');
        // Method only exists in 3.11+.
        if (method_exists('\core_payment\helper', 'get_success_url')) {
            $url = helper::get_success_url($component, $paymentarea, $itemid);
        } else if ($component == 'enrol_fee' && $paymentarea == 'fee') {
            require_once($CFG->dirroot . '/course/lib.php');
            $courseid = static::db()->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => $itemid]);
            if (!empty($courseid)) {
                $url = course_get_url($courseid);
            }
        }
        return $url;
    }

}
