<?php

namespace JambageCom\TransactorPaypal\Eid;

/***************************************************************
*  Copyright notice
*
*  (c) 2018 Franz Holzinger (franz@ttproducts.de)
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
 * @subpackage transactor
 */

class Listener {

    public function run () {
        \JambageCom\Div2007\Utility\FrontendUtility::init();

        $eIDutility = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Utility\EidUtility::class);
        $eIDutility->initTCA();
            // Make instance and call main():
        /** @var \JambageCom\TransactorPaypal\Api\Listener $SOBE */
        $SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\JambageCom\TransactorPaypal\Api\Listener::class);
        $result = $SOBE->main();
    }
}
