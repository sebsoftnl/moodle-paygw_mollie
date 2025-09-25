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
 * This module is responsible for Mollie payment status checking.
 *
 * @module     paygw_mollie/paymentstatus
 * @copyright  2021 RvD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import * as Notification from 'core/notification';
import * as Ajax from 'core/ajax';
import * as Str from 'core/str';

/**
 * Get carts.
 *
 * @param {Integer} pid
 * @return {Promise<>} jQuery promise
 */
const getStatus = (pid) => {
    const request = {
        methodname: 'paygw_mollie_get_payment_status',
        args: {pid: pid}
    };

    const promise = Ajax.call([request])[0];
    promise.fail(Notification.exception);
    return promise;
};

/**
 * @type {Object}
 */
const SELECTORS = {
    statusContainer: '#paygw_mollie_paymentstatus',
    hiddenContainer: '#paygw_redirect',
    continue: '#paygw_mollie_continuebutton',
    loading: '.paygw_mollie_paymentstatus .loader',
    longWaitNotify: '#paygw_mollie_paymentstatus .longwaitnotify'
};

/**
 * Initialize
 *
 * @param {Integer} pid
 * @param {Integer} checkInterval
 * @param {Integer} longWait
 */
export const init = (pid, checkInterval, longWait) => {
    let toId = null;
    const start = Date.now();
    const longWaitMs = 1000 * longWait;
    // Register click handler for "continue".
    $(SELECTORS.continue).on('click', () => {
        if (toId !== null) {
            // Clear function call in stack.
            clearTimeout(toId);
        }
    });
    const checker = async() => {
        if ((Date.now() - start) > longWaitMs) {
            checkInterval = 3 * checkInterval;
            // Show hidden.
            $(SELECTORS.longWaitNotify).removeClass('hidden');
        }
        let result = await getStatus(pid);

        Str.get_string(`payment${result.status}`, 'paygw_mollie')
            .then(str => {
                $(SELECTORS.loading).addClass('hidden');
                $(SELECTORS.statusContainer).html(`<div class="alert alert-${result.messagetype}">${str}</div>`);
            });
        if (!result.isfinalstate) {
            // Run again.
            toId = setTimeout(checker, checkInterval * 1000);
        } else {
            // Unhide.
            $(SELECTORS.hiddenContainer).removeClass('hidden');
        }
    };

    toId = setTimeout(checker, 1);
};
