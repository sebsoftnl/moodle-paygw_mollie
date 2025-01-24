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
 * Strings for component 'paygw_mollie', language 'en'
 *
 * File         paygw_mollie.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['apikey'] = 'Live API Key';
$string['apikey_help'] = 'Live API Key - this is the API key as provided by Mollie which is needed for live payments';
$string['apikeytest'] = 'Test API Key';
$string['apikeytest_help'] = 'Test API Key - this is the API key as provided by Mollie which is needed for test payments';
$string['cannotprocessstatus'] = 'Your payment has a status we cannot (yet) process. Please contact system administrator';
$string['err:assert:paymentrecordvariables'] = 'Invalid request: one or more paymentrecord variables do not match with the intended component, paymentarea or itemid';
$string['err:validatetransaction:component'] = 'Transaction invalid: component mismatch';
$string['err:validatetransaction:paymentarea'] = 'Transaction invalid: paymentarea mismatch';
$string['err:validatetransaction:itemid'] = 'Transaction invalid: itemid mismatch';
$string['err:validatetransaction:userid'] = 'Transaction invalid: user mismatch';
$string['err:synchronizestatus:args:invalid'] = 'Provide either the Mollie transaction, the internal record or both.';
$string['err:nopaymentmethods'] = 'You don\'t have any payment methods enabled for Mollie.';
$string['gatewaydescription'] = 'Mollie is an authorised payment gateway provider for processing credit card transactions.';
$string['gatewayname'] = 'Mollie';
$string['internalerror'] = 'An internal error has occurred. Please contact the system administrator.';
$string['payment:returnpage'] = 'Processing payment status.';
$string['paymentalreadypaid'] = 'Payment already performed';
$string['paymentsuccessful'] = 'Your payment was successful';
$string['paymentcancelled'] = 'Your payment was cancelled';
$string['paymentpending'] = 'Your payment is pending. We will process the payment status later';
$string['pluginname'] = 'Mollie';
$string['pluginname_desc'] = 'The Mollie plugin allows you to receive payments via Mollie.';
$string['privacy:metadata'] = 'The Mollie plugin does not store any personal data.';
$string['privacy:metadata:paygw_mollie'] = 'The Mollie payment plugin stores external transactionid\'s and payment references for the Moodle user needed to identity and synchronize payments.';
$string['privacy:metadata:paygw_mollie:userid'] = 'User ID';
$string['privacy:metadata:paygw_mollie:paymentid'] = 'Payment ID (internal)';
$string['privacy:metadata:paygw_mollie:component'] = 'Payment component';
$string['privacy:metadata:paygw_mollie:paymentarea'] = 'Payment area';
$string['privacy:metadata:paygw_mollie:itemid'] = 'Payment item ID';
$string['privacy:metadata:paygw_mollie:orderid'] = 'Mollie Order ID (external)';
$string['privacy:metadata:paygw_mollie:status'] = 'Order status name';
$string['privacy:metadata:paygw_mollie:testmode'] = 'Whether or not payment was done in test/sandbox mode';
$string['privacy:metadata:paygw_mollie:timecreated'] = 'Time the order record was created';
$string['privacy:metadata:paygw_mollie:timemodified'] = 'Time the order record was last updated';
$string['redirect-notify'] = 'Please note that starting a payment redirects you to an external Mollie page.';
$string['selectpaymentmethod'] = 'Select payment method';
$string['selectpaymentmethod_help'] = 'You can make a selection for the payment method here if you wish to do so.<br/>
If you don\'t select one, don\'t worry! In that case you will be able to select the payment method after you have been redirected to Mollie.';
$string['setting:useinternalzeropayments'] = 'Process zero payment internally?';
$string['setting:useinternalzeropayments_help'] = 'Whether or not to process payments internally. This should be left checked, Mollie\'s payment gateway will NOT accept a payment of 0.
When unchecked, the gateway will throw errors and the payment will ultimately not be performed or handled.';
$string['startpayment'] = 'Start payment';
$string['startpayment:failed:title'] = 'Payment could not be started.';
$string['startpayment:failed:btncancel'] = 'Close';
$string['testmode'] = 'Use test mode?';
$string['testmode_help'] = 'When in test mode, the Mollie payment gateway will use the Test API Key. Otherwise it will use the Live API Key';
$string['transactionrecordnotfound'] = 'Reference to this payment cannot be found in our system.';
$string['unknownerror'] = 'An unknown error has occurred. Please contact the system administrator.';
