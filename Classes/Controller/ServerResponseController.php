<?php

namespace JambageCom\TransactorPaypal\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Part of the tt_products (Shop System) extension.
 *
 * main class for taxajax
 *
 * @author  Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author  Franz Holzinger <franz@ttproducts.de>
 * @maintainer	Franz Holzinger <franz@ttproducts.de>
 * @package TYPO3
 * @subpackage tt_products
 *
 *
 */

use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;



use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;



class ServerResponseController {

    /**
    * @param ServerRequestInterface $request
    * @param ResponseInterface $response
    * @return ResponseInterface
    */
    public function processRequest (
        ServerRequestInterface $request
    )
    {
            // Make instance and call main():
        /** @var \JambageCom\TransactorPaypal\Api\Listener $SOBE */
        $SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\JambageCom\TransactorPaypal\Api\Listener::class);
        $result = $SOBE->main();

        return new NullResponse();
    }
}

