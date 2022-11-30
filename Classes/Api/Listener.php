<?php

namespace JambageCom\TransactorPaypal\Api;

/***************************************************************
*  Copyright notice
*
*  (c) 2019 Franz Holzinger (franz@ttproducts.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
* Called by the payment gateway after the payment has been made or an error has occured.
*
* @author	Franz Holzinger <franz@ttproducts.de>
* @package TYPO3
* @subpackage transactor_paypal
*/

define('TESTMODE', 1); // set this to 1 for your first trials

use JambageCom\TransactorPaypal\Configuration\Configuration;
use JambageCom\Transactor\Constants\State;

use TYPO3\CMS\Core\Utility\GeneralUtility;


class Listener extends \JambageCom\Transactor\Api\Listener {

    /**
    * Main function which creates the transaction record
    *
    * @return	void
    */
    public function main () {

        $gatewayExtKey = 'transactor_paypal';
        $referenceId = $_POST['custom'];
        $gatewayFactoryObj =
            \JambageCom\Transactor\Domain\GatewayFactory::getInstance();
        $gatewayFactoryObj->registerGatewayExtension($gatewayExtKey);
        $paymentMethod = 'paypal_webpayment_standard';
        $gatewayProxyObject =
            $gatewayFactoryObj->getGatewayProxyObject(
                $paymentMethod
            );
        $row = $gatewayProxyObject->getTransaction($referenceId);

        if (!$row) {
            return false;
        }
    // neu: Hier muss die Extension Konfiguration aus dem Datensatz eingelesen werden, nicht mehr, wie früher, aus der Extension und deren globalen Variable. Denn das Setup kann dieses nun überschreiben.

        if (isset($row['config_ext'])) {
             $conf = GeneralUtility::xml2array($row['config_ext']);
        } else if (
            defined('TYPO3_version') &&
            version_compare(TYPO3_version, '9.0.0', '>=')
        ) {
            $conf = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
            )->get('transactor_paypal');
        } else { // before TYPO3 9
            $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['transactor_paypal']);
        }

        // check that txn_id has not been previously processed

        Configuration::setConf($conf);
        $verificationError = '';
        $verificationDuplicate = false;
        $verified = false;

        $myPost = [];

        if(TESTMODE == 2) {
            $myPost = $_REQUEST;
        } else {
            // Read POST data
            // reading posted data directly from $_POST causes serialization
            // issues with array data in POST. Reading raw POST data from input stream instead.
            $raw_post_data = file_get_contents('php://input');
            $raw_post_array = explode('&', $raw_post_data);
            $myPost = [];
            foreach ($raw_post_array as $keyval) {
                $keyval = explode ('=', $keyval);
                if (count($keyval) == 2) {
                    $myPost[$keyval[0]] = urldecode($keyval[1]);
                }
            }
        }

        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-validate';

        if (is_array($myPost)) {
            foreach ($myPost as $key => $value) {
                $value = stripslashes($value);
                $value = urlencode($value);
                $req .= '&' . $key . '=' . $value;
            }
        }

        $paypalUrl = Configuration::getActionURI();

        $ch = curl_init($paypalUrl);

        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);

        if(TESTMODE == 1) {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }

        // CONFIG: Optional proxy configuration
// 			curl_setopt($ch, CURLOPT_PROXY, $proxy);
// 			curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);

// 		Set TCP timeout to 30 seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        // CONFIG: Please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set the directory path
        // of the certificate as shown below. Ensure the file is readable by the webserver.
        // This is mandatory for some environments.

// 			$cert = __DIR__ . "./cacert.pem";
// 			curl_setopt($ch, CURLOPT_CAINFO, $cert);

        $curlResult = curl_exec($ch);
        if (curl_errno($ch) != 0) { // cURL error

            if(TESTMODE == 1) {
                debug('Can\'t connect to PayPal to validate IPN message: ' . curl_error($ch), 'ERROR from PayPal IPN');
            }
            curl_close($ch);
            return false;
        } else {
            // Log the entire HTTP response if debug is switched on.
            if (TESTMODE == 1) {
                debug(curl_getinfo($ch, CURLINFO_HEADER_OUT) . ' for IPN payload: ' . $req, 'HTTP request of validation request');
                debug($curlResult, 'HTTP response of validation request:');

                // Split response headers and payload
                list($headers, $curlResult) = explode(chr(13) . chr(10) . chr(13) . chr(10), $curlResult, 2);
                debug ($headers, '$headers');
                debug ($curlResult, '$curlResult payload');
            }
            curl_close($ch);
        }

        // Inspect IPN validation result and act accordingly

        if (strpos($curlResult, 'VERIFIED') !== false) {
            $verified = true;
            $paymentStatus = $_POST['payment_status'];

            // check whether the payment_status is Completed or Pending if this is activated
            if (
                $paymentStatus ==  \JambageCom\TransactorPaypal\Constants\PaymentStatus::COMPLETED ||
                (
                    $conf['pendingVerification'] &&
                    $paymentStatus ==  \JambageCom\TransactorPaypal\Constants\PaymentStatus::PENDING
                )
            ) {
                // do nothing
            } else {
                $verificationError = 'wrong payment state "' . $paymentStatus . '"';
                $verified = false;
            }

            if (intval($row['state']) != State::IDLE) {
                $verificationError = 'wrong state';
                $verified = false;

                if (
                    $row['state'] == State::APPROVE_OK ||
                    $row['state'] == State::APPROVE_DUPLICATE
                ) {
                    $verificationDuplicate = true;
                }
            }

            // check that receiver_email is your PayPal email
            if ($_POST['receiver_email'] != $conf['business']) {
                $verificationError = 'wrong business email';
                $verified = false;
            }

            // check that the invoice number is correct
            if ($_POST['invoice'] != $row['orderuid']) {
                $verificationError = 'order uid';
                $verified = false;
            }
            $difference = $row['amount'] - $_POST['mc_gross'];

            // check that payment_amount/payment_currency are correct
            if (
                (
                    $difference >
                        (
                            $row['amount'] / 1000 + doubleval($conf['pdtAmountDifference']) // The taxed prices are added within PayPal and not the net prices. This leads to differences between the paid amount and the amount from the shop.
                        )
                ) ||
                $_POST['mc_currency'] != $row['currency']
            ) {
                $verificationError = 'wrong amount or currency';
                $verified = false;
            }

            // process payment and mark item as paid.

            if ($verified) {
                $state = State::APPROVE_OK;
                $state_time = time();
                $state_message = 'OK';
                $updateUID = $row['uid'];

                $fields = [];
                $fields['message'] = $state_message;
                $fields['state_time'] = $state_time;
                $fields['state'] = $state;

                $dbResult =
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        'tx_transactor_transactions',
                        'uid=' . $updateUID,
                        $fields
                    );

                $params = [];
                $params['row'] = $row;
                $params['testmode'] = false;
                $params['parameters'] = $_POST;

                $this->execute($params);
                if (TESTMODE == 1) {
                    debug($req, 'Verified IPN SUCCESS MESSAGE from PayPal IPN');
                }
            } else if ($verificationDuplicate) {
                $state = State::APPROVE_DUPLICATE;
                $state_time = time();
                $state_message = 'OK';
                $updateUID = $row['uid'];

                $fields = [];
                $fields['message'] = $state_message;
                $fields['state_time'] = $state_time;
                $fields['state'] = $state;

                $dbResult =
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        'tx_transactor_transactions',
                        'uid=' . $updateUID,
                        $fields
                    );
            } else {
                if (isset($row['config_ext'])) {
                    unset($row['config_ext']); // do not send secret data in an email
                }
                $fields = array_merge($row, $_POST);
                $errorMessage = 'PayPal IPN payment verification failure "' . $verificationError . '" for order #' . $row['orderuid'];

                \JambageCom\Transactor\Api\PaymentApi::sendErrorEmail(
                    $conf['business'],
                    'TYPO3 PayPal Extension',
                    $_POST['receiver_email'],
                    $errorMessage,
                    $fields,
                    'transactor_paypal'
                );

                if (TESTMODE == 1) {
                    debug($errorMessage, 'PayPal ERROR');
                }
            }
        } else if (strpos($curlResult, 'INVALID') !== false) {
            // log for manual investigation
            // Add business logic here which deals with invalid IPN messages
            if(TESTMODE == 1) {
                debug('Invalid IPN: ' . $req, 'FAILURE MESSAGE from PayPal IPN');
            }
        }

        return $verified;
    }
}

