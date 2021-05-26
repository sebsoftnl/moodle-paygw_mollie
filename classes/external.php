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
 * This class contains a list of webservice functions related to the Mollie payment gateway.
 *
 * File         external.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_mollie;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

use core_payment\helper;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/payment/gateway/mollie/thirdparty/Mollie/vendor/autoload.php');

/**
 * This class contains a list of webservice functions related to the Mollie payment gateway.
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_methods_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
        ]);
    }

    /**
     * Returns the config values required by the Mollie JavaScript SDK.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string[]
     */
    public static function get_methods(string $component, string $paymentarea, int $itemid): array {
        self::validate_parameters(self::get_methods_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        $config = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'mollie');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();
        $surcharge = helper::get_gateway_surcharge('mollie');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $mollie = new \Mollie\Api\MollieApiClient();
        if (!empty($config['testmode'])) {
            $mollie->setApiKey($config['apikeytest']);
        } else {
            $mollie->setApiKey($config['apikey']);
        }

        // Methods for the Payments API.
        $methods = $mollie->methods->allActive();
        $rs = [];
        foreach ($methods as $method) {
            $rs[] = (object)[
                'id' => $method->id,
                'description' => $method->description,
                'minamount' => $method->minimumAmount,
                'maxamount' => $method->maximumAmount,
                'status' => $method->status,
                'enabled' => ($amount >= $method->minimumAmount->value && $amount <= $method->maximumAmount->value),
                'images' => $method->image,
                'hasbanks' => 0 // Reserved for future use.
            ];
        }

        return $rs;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function get_methods_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Payment method ID'),
                'description' => new external_value(PARAM_TEXT, 'Payment method description'),
                'minamount' => new external_single_structure([
                    'value' => new external_value(PARAM_FLOAT, 'Payment method minimum amount'),
                    'currency' => new external_value(PARAM_ALPHA, 'currency'),
                ]),
                'maxamount' => new external_single_structure([
                    'value' => new external_value(PARAM_FLOAT, 'Payment method maximum amount'),
                    'currency' => new external_value(PARAM_ALPHA, 'currency'),
                ]),
                'status' => new external_value(PARAM_TEXT, 'Payment method status'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether or not this is enabled based on the min/max amount'),
                'images' => new external_single_structure([
                    'size1x' => new external_value(PARAM_URL, 'size 1X image'),
                    'size2x' => new external_value(PARAM_URL, 'size 2X image'),
                    'svg' => new external_value(PARAM_URL, 'SVG image', VALUE_OPTIONAL),
                    ], 'images')
            ])
        );
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea
     * @param int $itemid An internal identifier that is used by the component
     * @param string $description
     * @param string $paymentmethodid Payment method ID
     * @param int $bankid bank identifier|reserved for future use
     * @return array
     */
    public static function create_payment(string $component, string $paymentarea, int $itemid,
            string $description, string $paymentmethodid = null, int $bankid = null): array {
        global $USER, $DB, $CFG;

        $params = self::validate_parameters(self::create_payment_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'description' => $description,
            'paymentmethodid' => $paymentmethodid,
            'bankid' => $bankid,
        ]);

        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'mollie');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();
        $surcharge = helper::get_gateway_surcharge('mollie');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $mollie = new \Mollie\Api\MollieApiClient();
        if (!empty($config->testmode)) {
            $mollie->setApiKey($config->apikeytest);
        } else {
            $mollie->setApiKey($config->apikey);
        }

        try {
            // Typical filth: we want to be able to refer the record to the payment.
            // So we create the record, create the payment and then update the record again.
            $time = time();
            $record = (object)[
                'userid' => $USER->id,
                'component' => $component,
                'paymentarea' => $paymentarea,
                'itemid' => $itemid,
                'orderid' => -1,
                'status' => 'INIT',
                'statuscode' => 0,
                'testmode' => empty($config->testmode) ? 0 : 1,
                'timecreated' => $time,
                'timemodified' => $time
            ];
            $record->id = $DB->insert_record('paygw_mollie', $record);

            $urlparams = [
                'component' => $component,
                'paymentarea' => $paymentarea,
                'itemid' => $itemid,
                'internalid' => $record->id
            ];
            $returnurl = new moodle_url($CFG->wwwroot . '/payment/gateway/mollie/return.php', $urlparams);
            $exchangeurl = new moodle_url($CFG->wwwroot . '/payment/gateway/mollie/xchange.php', $urlparams);

            // Set Mollie payment variables.
            $paymentparams = [
                'amount' => (object)[
                    'value' => format_float($amount, 2, false),
                    'currency' => $currency
                ],
                'description' => $description,
                'redirectUrl' => $returnurl->out(false),
                'webhookUrl' => $exchangeurl->out(false),
                'metadata' => (object)[
                    'tool' => 'moodle/paygw_mollie-v'.get_config('paygw_mollie', 'version'),
                    'extra1' => "{$component}|{$paymentarea}|{$itemid}|{$USER->id}"
                ]
            ];
            if (!empty($paymentmethodid)) {
                $paymentparams['method'] = $paymentmethodid;
            }

            $createpaymentresult = $mollie->payments->create($paymentparams);
            $redirecturl = $createpaymentresult->getCheckoutUrl();

            // Update record.
            $record->orderid = $createpaymentresult->id;
            $record->id = $DB->update_record('paygw_mollie', $record);

            $success = true;

        } catch (\Exception $e) {
            debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $success = false;
            $message = get_string('internalerror', 'paygw_mollie') . $e->getMessage();
            $redirecturl = null;
        }

        return [
            'success' => $success,
            'message' => $message,
            'redirecturl' => $redirecturl,
        ];
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function create_payment_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'description' => new external_value(PARAM_TEXT, 'Payment description'),
            'paymentmethodid' => new external_value(PARAM_TEXT, 'Payment method ID', VALUE_DEFAULT, null, NULL_ALLOWED),
            'bankid' => new external_value(PARAM_INT, 'Bank ID (reserved for future use)', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function create_payment_returns() {
        return new external_function_parameters([
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_RAW,
                    'Message (usually the error message). Unused or not available if everything went well',
                    VALUE_OPTIONAL),
            'redirecturl' => new external_value(PARAM_RAW, 'Message (usually the error message).', VALUE_OPTIONAL),
        ]);
    }

}
