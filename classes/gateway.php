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
 * Contains class for Mollie payment gateway.
 *
 * File         gateway.php
 * Encoding     UTF-8
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_mollie;

/**
 * The gateway class for Mollie payment gateway.
 *
 * @package     paygw_mollie
 *
 * @copyright   2021 Ing. R.J. van Dongen
 * @author      Ing. R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {

    /**
     * Return a list of supported currencies for the Mollie gateway.
     *
     * @return array
     */
    public static function get_supported_currencies(): array {
        // 3-character ISO-4217: https://en.wikipedia.org/wiki/ISO_4217#Active_codes.
        return [
            'EUR',
        ];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'apikey', get_string('apikey', 'paygw_mollie'));
        $mform->setType('apikey', PARAM_TEXT);
        $mform->addHelpButton('apikey', 'apikey', 'paygw_mollie');

        $mform->addElement('text', 'apikeytest', get_string('apikeytest', 'paygw_mollie'));
        $mform->setType('apikeytest', PARAM_TEXT);
        $mform->addHelpButton('apikeytest', 'apikeytest', 'paygw_mollie');

        $mform->addElement('advcheckbox', 'testmode', get_string('testmode', 'paygw_mollie'), 0);
        $mform->addHelpButton('testmode', 'testmode', 'paygw_mollie');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        // Live mode testing.
        if ($data->enabled && (empty($data->apikey) && empty($data->testmode))) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
        if (empty($data->apikey) && empty($data->testmode)) {
            $errors['apikey'] = get_string('required');
        }
        // Test mode testing.
        if (empty($data->apikeytest) && !empty($data->testmode)) {
            $errors['apikeytest'] = get_string('required');
        }
    }
}
