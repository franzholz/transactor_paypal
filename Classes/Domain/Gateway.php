<?php

namespace JambageCom\TransactorPaypal\Domain;

/***************************************************************
*  Copyright notice
*
*  (c) 2019 Franz Holzinger (franz@ttproducts.de)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
 * payment_PAYPAL.php
 *
 * This script handles payment via the PAYPAL gateway.
 *
 *
 * PAYPAL:	https://www.paypal.com
 *
 * @author	Franz Holzinger <franz@ttproducts.de>
 * @package TYPO3
 * @subpackage transactor_paypal
 *
 *
 */

use JambageCom\Transactor\Constants\Field;
use JambageCom\Transactor\Constants\GatewayMode;
use JambageCom\Transactor\Constants\State;
use JambageCom\Transactor\Constants\Message;



use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;


class Gateway extends \JambageCom\Transactor\Domain\GatewayBase {
    protected $gatewayKey = 'transactor_paypal';
    protected $extensionKey = 'transactor_paypal';

    public $action = 0;
    public $paymentMethod = '';
    public $callingExtension = '';
    public $processed = false;

    // Settings for paypal
    public $sendBasket = false;	// Submit detailled basket informations like single products
    public $parameters = array();

        // Setup array for modifying the inputs

    public $modifyRules = array(
    // German umlauts:
//         '/Ä/' => 'Ae',
//         '/ä/' => 'ae',
//         '/Ü/' => 'Ue',
//         '/ü/' => 'ue',
//         '/Ö/' => 'Oe',
//         '/ö/' => 'oe',
//         '/ß/' => 'ss',
    // Spanish characters:
//         '/ñ/' => 'n',
//         '/Ñ/' => 'N',
//         '/¿/' => 'c',
//         '/¡/' => 'i',
//         '/á/' => 'a',
//         '/Á/' => 'A',
//         '/é/' => 'e',
//         '/É/' => 'E',
//         '/í/' => 'i',
//         '/Í/' => 'I',
//         '/ó/' => 'o',
//         '/Ó/' => 'O',
//         '/ú/' => 'u',
//         '/Ú/' => 'U',
    // Special characters
        '/•/' => '*',
        '/“/' => '"',
        '/”/' => '"',
        '/•/' => '.',
        '/’/' => '\'',
        '/&nbsp;/' => ' ',
    );

    public function setConf ($conf) {
        \JambageCom\TransactorPaypal\Configuration\Configuration::setConf($conf);
        parent::setConf($conf);
    }

    /**
    * Sets the payment details. Which fields can be set usually depends on the
    * chosen / supported gateway mode. TX_TRANSACTOR_GATEWAYMODE_FORM does not
    * allow setting credit card data for example.
    *
    * @param	array		$detailsArr: The payment details array
    * @return	boolean		Returns true if all required details have been set
    * @access	public
    */
    public function transactionSetDetails ($detailsArr) {
        $conf = $this->getConf();
        $this->setSendBasket($conf['sendBasket']);

        $this->config['currency_code'] = $detailsArr['transaction']['currency'];
        if (ord($this->config['currency_code']) == 128) { // 'euro symbol'
            $this->config['currency_code'] = 'EUR';
        }
        return parent::transactionSetDetails($detailsArr);
    }

    /**
    * Returns the form action URI to be used in mode GatewayMode::FORM.
    * This is used by PayPal and DIBS
    * @return	string		Form action URI
    * @access	public
    */
    public function transactionFormGetActionURI () {
        $result = false;
        if ($this->getGatewayMode() == GatewayMode::FORM) {
            $result = \JambageCom\TransactorPaypal\Configuration\Configuration::getActionURI();
        }

        return $result;
    }

    /**
    * Returns an array of field names and values which must be included as hidden
    * fields in the form you render use mode TX_TRANSACTOR_GATEWAYMODE_FORM.
    *
    * @return	array		Field names and values to be rendered as hidden fields
    * @access	public
    */
    public function transactionFormGetHiddenFields () {
        $detailsArray = $this->getDetails();
        $basket = $detailsArray['basket'];
        $address = $detailsArray['address'];
        $total = $detailsArray['total'];
        $transaction = $detailsArray['transaction'];
        $conf = $this->getConf();

        $fieldsArray = $this->getConfig();
        $fieldsArray['upload'] = '1';
        $fieldsArray['charset'] = 'UTF-8';
        $fieldsArray['business'] = $conf['business'];
        $fieldsArray['return'] = $transaction['successlink'];
        $fieldsArray['cancel_return'] = ($transaction['faillink'] ? $transaction['faillink'] : $transaction['returi']);
        $fieldsArray['notify_url'] = $transaction['notifyurl'];
        $fieldsArray['invoice'] = $transaction['orderuid'];
        $fieldsArray['custom'] = $this->getReferenceUid();

        // *******************************************************
        // Set article vars if selected
        // *******************************************************
        $modSourceArray = array_keys($this->modifyRules);
        $modDestinationArray = array_values($this->modifyRules);

        // neu Anfang für Gutschein Rabatt
        $discountRate = 0;
        if (
            isset($basket) &&
            is_array($basket) &&
            isset($basket['VOUCHER']) &&
            is_array($basket['VOUCHER']) &&
            isset($basket['VOUCHER']['0']) &&
            is_array($basket['VOUCHER']['0']) &&
            isset($basket['VOUCHER']['0']['amount']) &&
            isset($total) &&
            is_array($total) &&
            isset($total['goodstax'])
        ) {
            $discountAmount = -$basket['VOUCHER']['0']['amount'];
            $discountRate = ($discountAmount / $total['goodstax']) * 100;
            unset($basket['VOUCHER']);
        }

        if ($this->useBasket() && is_array($basket)) {
            $fieldsArray['cmd'] = '_cart';
            $count = 0;

            foreach($basket as $sort => $item) {
                $count++;
                $paypalItem = [];
                $paypalItem['item_name'] = $item[Field::NAME];
                $paypalItem['quantity'] = $item[Field::QUANTITY];
                $paypalItem['amount'] = $item[Field::PRICE_NOTAX];
                $paypalItem['payment'] = $item[Field::PAYMENT_NOTAX]
                ?? 0;
                $paypalItem['shipping'] = $item[Field::SHIPPING_NOTAX] ?? 0;
                $paypalItem['handling'] = $item[Field::HANDLING_NOTAX] ?? 0;
                $paypalItem['taxpercent'] = $item[Field::TAX_PERCENTAGE];
        // consider the taxes for the discount:
                $paypalItem['tax'] = $item[Field::PRICE_ONLYTAX];
                $paypalItem['totaltax'] = $item[Field::PRICE_TOTAL_ONLYTAX];
                $paypalItem['item_number'] = $item[Field::ITEMNUMBER];

                $itemDiscount = $discountRate * (1 + $item[Field::TAX_PERCENTAGE] / 100);
                $paypalItem['discount_rate'] = $itemDiscount;
                $paypalItem['discount_rate2'] = $itemDiscount;

                foreach($paypalItem as $itemField => $itemValue) {
                    if (
                        $itemField == 'shipping' ||
                        $itemField == 'handling'
                    ) {
                        $value = $itemValue;
                    } else {
                        $value = preg_replace($modSourceArray, $modDestinationArray, $itemValue);
                    }
                    $fieldsArray[$itemField . '_' . $count] = $value;
                }
            }
        } else {
            if ($conf['createNewUser']) {
                $fieldsArray['cmd'] = '_ext-enter';
                $fieldsArray['redirect_cmd'] = '_xclick';
            }
            $order = $detailsArray['order'];

            $fieldsArray['amount'] = $total['goodstax'];
            $fieldsArray['shipping'] = $total['shippingtax'];
            $fieldsArray['handling'] = $total['handlingtax'];
            if ($total['paymenttax']) {
                $fieldsArray['handling'] += $total['paymenttax'];
            }
            $fieldsArray['item_name'] = $order['orderNumber'];
        }

        if (
            (
                $conf['sendPersonInfo'] ||
                $conf['createNewUser']
            ) &&
            is_array($address['person'])
        ) {
            foreach ($address['person'] as $k => $v) {
                $value = preg_replace($modSourceArray, $modDestinationArray, $v);
                $fieldsArray[$k] = $value;
            }
            $fieldsArray['payer'] = $address['person']['email'];
        }

        $finalFieldsArray = array();
        foreach($fieldsArray as $k => $v) {
            if ($v != '') {
                $finalFieldsArray[$k] = $v;
            }
        }

        if (
            is_array($detailsArray) &&
            isset($detailsArray['options']) &&
            is_array($detailsArray['options'])
        ) {
            foreach ($detailsArray['options'] as $k => $v) {
                if ($v != '') {
                    $finalFieldsArray[$k] = $v;
                }
            }
        }

        return $finalFieldsArray;
    }

    /**
    * Returns the results of a processed transaction
    *
    * @param	string		$reference
    * @return	array		Results of a processed transaction
    * @access	public
    */
    public function transactionGetResults ($reference) {

        $resultsArray = $this->getResultsArray();
        $dbRow = $this->getTransaction($reference);
        $paymentStatus = GeneralUtility::_GET('st');
        $conf = $this->getConf();

        if (
            !empty($resultsArray) ||
            !is_array($dbRow)
        ) {
            return $resultsArray;
        }

        if (
            $paymentStatus ==  \JambageCom\TransactorPaypal\Constants\PaymentStatus::COMPLETED ||
            (
                $conf['pendingVerification'] &&
                $paymentStatus ==  \JambageCom\TransactorPaypal\Constants\PaymentStatus::PENDING
            )
        ) {
            if (
                $conf['pdtToken'] != '' &&
                $this->getGatewayMode() == GatewayMode::FORM
            ) {
                $resultsArray =
                    $this->requestPDT(
                        GeneralUtility::_GET('tx'),
                        $reference,
                        $dbRow
                    );
            } else {
                $amt = GeneralUtility::_GET('amt');
                $resultsArray = $dbRow;

                if (abs($dbRow['amount'] - $amt) < 1) {
                    $resultsArray['message'] = 'no PDT';
                    $resultsArray['state'] = State::APPROVE_OK;
                } else {
                    $resultsArray['message'] = 'wrong amount';
                    $resultsArray['state'] = State::APPROVE_NOK;
                }
            }
        } else {
            $resultsArray = $dbRow;

            if (
                $dbRow['message'] !=
                Message::NOT_PROCESSED
            ) {
                // ignore any former errors
                $resultsArray['message'] = Message::NOT_PROCESSED;
                $resultsArray['state'] = State::IDLE;

                $res =
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        $this->getTablename(),
                        'reference = ' .
                            $GLOBALS['TYPO3_DB']->fullQuoteStr(
                                $reference,
                                $this->getTablename()
                            ),
                        $resultsArray
                    );
            }
        }

        $this->setResultsArray($resultsArray);

        return $resultsArray;
    }

    /**
    * Returns the parameters of the recently processed transaction
    *
    * @return	array		parameters of the last processed transaction
    * @access	public
    */
    public function transactionGetParameters () {
        return $this->parameters;
    }

    public function setParameters ($parameters) {
        $this->parameters = $parameters;
    }

    public function transactionFailed ($resultsArray) {

        $result = false;
        if ($resultsArray['state'] == State::APPROVE_NOK) {
            $result = true;
        }

        return $result;
    }

    // *****************************************************************************
    // Helpers Payment Data Transfer (PDT)
    // *****************************************************************************
    public function requestPDT ($tx, $reference, array $dbRow) {
        if ($tx == '') {
            return false;
        }
        $result = false;
        $updateDatabase = true;
        $conf = $this->getConf();
        $config = $this->getConfig();
        $formuri = $this->transactionFormGetActionURI();
        $languageObj = GeneralUtility::makeInstance(\JambageCom\Transactor\Api\Localization::class);
        $resultsArray = array();
        $row =
            $this->getEmptyResultsArray(
                $reference,
                $config['currency_code']
            );

        $row = array_merge($row, $dbRow);

        // ignore any former errors
        if (
            $row['state'] != State::APPROVE_DUPLICATE &&
            $row['state'] != State::APPROVE_OK
        ) {
            $row['state'] = State::IDLE;
        }
        $row['user'] = '';
        $row['message'] = $languageObj->getLabel(
            'error_transaction_no'
        );

        if (
            $row['state'] == State::IDLE
        ) {
            // Perform transaction check
            $uriSplitted = parse_url($formuri);

            // Read the post from PayPal and add 'cmd'
            $req = 'cmd=_notify-synch';
            $req .= '&tx=' . $tx . '&at=' . $conf['pdtToken'];

            $host = strtolower($uriSplitted['host']);

            // post back to PayPal system to validate

            $header .= 'POST ' . $uriSplitted['path'] . ' HTTP/1.1' . chr(13) . chr(10);
            $header .= 'Host: ' . $host . chr(13) . chr(10);
            $header .= 'Content-Type: application/x-www-form-urlencoded' . chr(13) . chr(10);
            $header .= 'Content-Length: ' . strlen($req) . chr(13) . chr(10);
            $header .= 'Connection: close' . chr(13) . chr(10);
            $header .= chr(13) . chr(10);

            $port = 80;

            if (extension_loaded('openssl')) {
                $port = 443;
                $host = 'ssl://' . $host;
            }
            $fp = fsockopen($host, $port, $errno, $errstr, 30);

            if ($fp) {
                fputs($fp, $header . $req);	// Post request command

                // read the body data
                $res = '';
                $headerdone = false;
                $out = 'GET / HTTP/1.1' . chr(13) . chr(10);
                $out .= 'Host: ' . $host . chr(13) . chr(10);
                $out .= 'Connection: Close' . chr(13) . chr(10). chr(13) . chr(10);
                fwrite($fp, $out);

                while (!feof($fp)) {
                    $line = fgets($fp, 1024);

                    if (strcmp($line, chr(13) . chr(10)) == 0) {
                        // read the header
                        $headerdone = true;
                    } else if ($headerdone) {
                        // Header has been read. Now read the contents
                        $res .= $line;
                    }
                }
                fclose($fp);
                $lines = explode(chr(10), $res);
                // Check if transaction was successfull
                if (
                    strcmp($lines[0], 'SUCCESS') == 0 ||
                    (
                        count ($lines) > 1 &&
                        strcmp($lines[1], 'SUCCESS') == 0
                    )
                ) {
                    for ($i = 1; $i < count($lines); $i++) {
                        list($key, $val) = explode('=', $lines[$i]);
                        $resultsArray[trim(urldecode($key))] = trim(urldecode($val));
                    }
                    $this->setParameters($resultsArray);

                    if ($reference == $resultsArray['custom']) {

                        $difference = $resultsArray['mc_gross'] - $row['amount'];

                        if (
                            (
                                abs($difference) <
                                    (
                                        $row['amount'] / 1000 + doubleval($conf['pdtAmountDifference'])
                                        // The taxed prices are added within PayPal and not the net prices. This leads to differences between the paid amount and the amount from the shop.
                                    )
                            ) &&
                            $resultsArray['mc_currency'] == $row['currency']
                        ) {
                            // Transaction was processed correctly
                            $result = true;
                            $row['message'] = $resultsArray['payment_status'];
                            $row['user'] .= ':' . $resultsArray['payment_type'];
                            $row['state'] = State::APPROVE_OK;
                            $row['gatewayid'] = $resultsArray['txn_id'] . ': ' . $resultsArray['txn_type'] . ' ' . $resultsArray['payment_date'];
                        } else {
                            $row['message'] = Message::WRONG_AMOUNT . ': ' . $resultsArray['mc_gross'];
                            $row['state'] = State::CREDIT_NOK;
                            $row['gatewayid'] = $resultsArray['txn_id'] . ': ' . $resultsArray['txn_type'] . ' ' . $resultsArray['payment_date'];
                        }
                    } else {
                        // Received wrong transaction
                        $row['message'] = Message::WRONG_TRANSACTION;
                        $row['state'] = State::APPROVE_NOK;
                        $row['gatewayid'] = $resultsArray['txn_id'] . ': ' . $resultsArray['txn_type'] . ' ' . $resultsArray['payment_date'];
                    }
                } else {
                    // Something goes wrong
                    $row['message'] = 'PDT ' . $lines[0] . $lines[1];
                    // $row['user'] .= ':'.$resultsArray['payment_type'];
                    $row['state'] = State::APPROVE_NOK;
                }
            } else {
                // File Error
                $row['message'] = 'A connection to the PayPal url "' . $formuri . '" could not be established:';
                if ($errno) {
                    $row['message'] .= ' error #' . $errno . ' (' . $errstr . ')';
                }

                $row['message'] .= ' tx = ' . htmlspecialchars($tx);
                $row['user'] .= ':' . $errstr;
                $row['state'] = State::APPROVE_NOK;
            }
        } else if (
            $row['state'] == State::APPROVE_OK
        ) {
            $row['state'] = State::APPROVE_DUPLICATE;
            $result = true;
        } else if (
            $row['state'] == State::APPROVE_DUPLICATE
        ) {
            $result = true;
            $updateDatabase = false;
        }

        if ($updateDatabase) {
            $res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                $this->getTablename(),
                'reference = ' .
                    $GLOBALS['TYPO3_DB']->fullQuoteStr(
                        $reference,
                        $this->getTablename()
                    ),
                $row
            );
        }

        if (!$result) {
            $fields = array_merge($row, $resultsArray);
            \JambageCom\Transactor\Api\PaymentApi::sendErrorEmail(
                $conf['business'],
                'TYPO3 PayPal Extension',
                $conf['business'],
                'PayPal PDT payment verification failure order #' . $row['orderuid'],
                $fields,
                $this->getExtensionKey()
            );
        }

        return $row;
    }

    /**
    * Calculates the payment costs
    * see http://www.gregledet.net/ppfcm.html
    *
    * @param	array		configuration
    * @param	float		total amount to pay
    * @param	string		ISO3 code of seller
    * @param	string		ISO3 code of buyer
    * @return	float		payment costs
    * @access	public
    */
    public function getCosts (
        $confScript,
        $amount,
        $iso3Seller,
        $iso3Buyer
    ) {
        $result = (float) 0;

// PayPal-Gebühr = Grundgebühr + %-Satz vom Betrag. Z.Beispiel 35 ct + 2% * (Betrag)
//
// Aufgabe: Aufschlag x beim Kunden, so dass (Betrag + x) - PayPal-Gebühr(Betrag + x) = Betrag beim Händler
// oder als Formel-Aufgabe: B = Betrag, g = Grundgebühr, p = Prozentsatz
// Der Kunde zahlt B + x, der Händler möchte von PayPal B ausgezahlt bekommen, PayPal zahlt aus (B + x) - p*(B + x ) - g
// B = B + x - p*(B + x) - g
// 0 = x - p*B - p*x - g
// x - p*x = p*B + g
// x = (p*B + g)/(1 - p)
//
// Beispiel Grundgebühr = 0,35€; Prozente = 0,02; Betrag = 100€
// x = (0,02 *1000€ + 0,35€)/(1 - 0,02) = 20,35 € / 0,98 = 20,77€. Kunde zahlt 1020,77€
// PayPal zieht ab:  0,02*1020,77 und 0,35€  :  20,41€ + 0,35€ = 20,76€
        $businessCharge = true;
        if (
            $businessCharge
        ) {
            $basicFee = 0.35;
            $basicBreak = false;
            $percentalFee = 0;
            $percentalBaseFee = 1.9;

            if ($iso3Seller == $iso3Buyer) {	// A3.1.2
                $percentalFee = 1.9;
            } else {
// XML Datei percentalfee.xml auslesen
// Für die basicfee muss die static_countries Tabelle ausgelesen werden.
// cn_currency_iso_3
// XML Datei basicfee.xml auslesen
                $filename = 'Resources/Private/Calculation/percentalfee.xml';
                $file = GeneralUtility::getFileAbsFileName(\TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix(ExtensionManagementUtility::extPath($this->getExtensionKey())) . $filename);

                if ($file && @is_file($file)) {

                    $xml = GeneralUtility::getUrl($file);
                    $xmlObj = \JambageCom\Div2007\Utility\XmlUtility::xml_to_object($xml);
                    foreach ($xmlObj->children as $zoneObj) {
                        if ($basicBreak) {
                            break;
                        }

                        if (
                            isset($zoneObj->attributes['id'])
                        ) {
                            $zone = $zoneObj->attributes['id'];

// 							percentage
// 							title

                            foreach ($zoneObj->children as $countriesObj) {
                                if ($basicBreak) {
                                    break;
                                }
                                if ($countriesObj->name == 'percentage') {
                                    $percentalFee = $percentalBaseFee + $countriesObj->content;
                                }

                                if (
                                    isset($countriesObj->attributes['id'])
                                ) {
                                    $countries = $countriesObj->attributes['id'];
                                    foreach ($countriesObj->children as $countryObj) {
                                        if (
                                            isset($countryObj->attributes['id'])
                                        ) {
                                            $country = $countryObj->attributes['id'];
                                            if (
                                                $country == $iso3Buyer
                                            ) {
                                                $basicBreak = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

// XML Datei basicfee.xml auslesen
            $filename = 'Resources/Private/Calculation/basicfee.xml';
            $file = GeneralUtility::getFileAbsFileName(\TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix(ExtensionManagementUtility::extPath($this->getExtensionKey())) . $filename);

            if ($file && @is_file($file)) {

                $xml = GeneralUtility::getUrl($file);
                $xmlObj = \JambageCom\Div2007\Utility\XmlUtility::xml_to_object($xml);
                $countryRow =
                    \tx_div2007_staticinfotables::fetchCountries(
                        '',
                        '',
                        $iso3Seller
                    );
                if (
                    !$countryRow ||
                    !$countryRow['0']['cn_currency_iso_3']
                ) {
                    return false;
                }
                $sellerCurrency = $countryRow['0']['cn_currency_iso_3'];

                foreach ($xmlObj->children as $currencyObj) {
                    if (
                        isset($currencyObj->attributes['id'])
                    ) {
                        $currency = $currencyObj->attributes['id'];
                        if (
                            $currency == $sellerCurrency &&
                            isset($currencyObj->children) &&
                            is_array($currencyObj->children) &&
                            !empty($currencyObj->children)
                        ) {
                            foreach ($currencyObj->children as $childObj) {
                                if (
                                    $childObj->name == 'price' &&
                                    isset($childObj->content)
                                ) {
                                    $basicFee = $childObj->content;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if ($percentalFee == 1) {
                throw new RuntimeException('Error in PayPal extension: invalid percental fee 100%');
            }

            // TODO: do the price calculation
            $percentalFee = floatval($percentalFee) / 100;
            $result = floatval(($percentalFee * $amount + $basicFee) / (1 - $percentalFee));
        }

        return $result;
    }
}
