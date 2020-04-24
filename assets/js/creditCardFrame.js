/**
 * Payment card script
 *
 * Script to handle the communication with the iFrame in order to submit the form from outside the iFrame
 *
 * @license Use of this software requires acceptance of the License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 * @link https://dev.heidelpay.de/woocommerce
 * @author David Owusu
 * @category WOOCOMMERCE
 */

jQuery(document).ready(function(){
        /**
         * Add an event listener to form submit, which will execute the sendMessage function
         */
        if (paymentFrameForm.addEventListener) {// W3C DOM
            paymentFrameForm.addEventListener('submit', sendMessage);
        } else if (paymentFrameForm.attachEvent) { // IE DOM
            paymentFrameForm.attachEvent('onsubmit', sendMessage);
        }

        /**
         * Add an event listener to your webpage, which will receive the response message from payment server.
         */
        if (window.addEventListener) { // W3C DOM
            window.addEventListener('message', receiveMessage);
        } else if (window.attachEvent) { // IE DOM
            window.attachEvent('onmessage', receiveMessage);
        }
    }
)


/**
 * Define send Message function
 * This function will collect each inpunt inside the form and then submit
 * the iframe to the payment server. Please note that it is not allowed to submit
 * credit or debit card information form your webpage.
 */

function sendMessage(e) {

    if(e.preventDefault) { e.preventDefault(); }
    else { e.returnValue = false; }

    var data = {};
    /**
     * Get the iFrame element
     */
    var paymentFrameIframe = document.getElementById('paymentFrameIframe');
    var targetOrigin = getDomainFromUrl(paymentFrameIframe);

    /**
     * Send html postmessage to payment frame
     */
    paymentFrameIframe.contentWindow.postMessage(JSON.stringify(data), targetOrigin);
}

/**
 * Function to get the domain from a given url
 */
function getDomainFromUrl(paymentIframe) {
    /**
     * Get hostname and protocol from paymentIframe
     */
    var url = paymentIframe.getAttribute("src");
    if (url === null) {
        url = paymentIframe.getAttribute("data-src");
    }
    var arr = url.split("/");
    return arr[0] + "//" + arr[2];
}

/**
 * Define receiveMessage function
 *
 * This function will receive the response message form the payment server.
 */
function receiveMessage(e) {
    /**
     * Get the iFrame element
     */
    var paymentFrameIframe = document.getElementById('paymentFrameIframe');
    var targetOrigin = getDomainFromUrl(paymentFrameIframe);
    if (e.origin !== targetOrigin) {
        return;
    }

    var antwort = JSON.parse(e.data);
}