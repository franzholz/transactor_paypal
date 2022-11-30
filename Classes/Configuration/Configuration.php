<?php

namespace JambageCom\TransactorPaypal\Configuration;

/***************************************************************
*  Copyright notice
*
*  (c) 2017 Franz Holzinger <franz@ttproducts.de>
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
* Part of the transactor_paypal extension.
*
* functions for the configuration
*
* @author  Franz Holzinger <franz@ttproducts.de>
* @package TYPO3
* @subpackage transactor_paypal
*
*/

class Configuration {
    static private $conf = array();

    static public function setConf ($conf)
    {
        self::$conf = $conf;
    }

    static public function getConf ()
    {
        return self::$conf;
    }

    static public function getActionURI ()
    {
        $conf = self::getConf();
        $production = 0;
        if (
            isset($conf['production']) &&
            $conf['production'] == '1'
        ) {
            $production = 1;
        }

        // Post IPN data back to PayPal to validate the IPN data is genuine
        // Without this step anyone can fake IPN data
        $urlArray = array(
            '0' => 'https://www.sandbox.Paypal.com/cgi-bin/webscr',
            '1' => 'https://www.Paypal.com/cgi-bin/webscr'
        );
        $result = $urlArray[$production];

        return $result;
    }
}
