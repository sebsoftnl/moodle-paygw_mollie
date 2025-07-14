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

use Mollie\Api\Resources\Payment;

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
     * Assert payment record variables
     *
     * @param stdClass $record stored mollie payment record
     * @param string $component
     * @param string $paymentarea
     * @param string $itemid
     * @throws moodle_exception
     */
    public static function assert_payment_record_variables($record, $component, $paymentarea, $itemid) {
        static::assert($record->component == $component && $record->paymentarea == $paymentarea && $record->itemid == $itemid,
            'err:assert:paymentrecordvariables', 'paygw_mollie'
        );
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
    public static function synchronize_status(?Payment $transaction = null,
            ?stdClass $transactionrecord = null) {
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
        global $CFG, $DB;
        // Find redirection.
        $url = new moodle_url('/');
        // Method only exists in 3.11+.
        if (method_exists('\core_payment\helper', 'get_success_url')) {
            $url = helper::get_success_url($component, $paymentarea, $itemid);
        } else if ($component == 'enrol_fee' && $paymentarea == 'fee') {
            require_once($CFG->dirroot . '/course/lib.php');
            $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => $itemid]);
            if (!empty($courseid)) {
                $url = course_get_url($courseid);
            }
        }
        return $url;
    }

    /**
     * Process zero payment.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return bool
     */
    public static function process_zero_payment(string $component, string $paymentarea, int $itemid, int $userid) {
        global $DB;

        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'mollie');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('mollie');

        $currency = $payable->get_currency();
        $amount = \core_payment\helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        // Save (zero)payment.
        $paymentid = \core_payment\helper::save_payment($payable->get_account_id(), $component,
                $paymentarea, $itemid, $userid, $amount, $currency, 'mollie');

        // Typical filth: we want to be able to refer the record to the payment.
        // So we create the record, create the payment and then update the record again.
        $time = time();
        $record = (object) [
            'userid' => $userid,
            'paymentid' => $paymentid,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'orderid' => -1,
            'status' => 'ZEROPAYMENT',
            'testmode' => empty($config->testmode) ? 0 : 1,
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        $record->id = $DB->insert_record('paygw_mollie', $record);

        // Deliver ORDER.
        return \core_payment\helper::deliver_order($component, $paymentarea, $itemid, $paymentid, $userid);
    }

}
