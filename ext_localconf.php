<?php
defined('TYPO3') || die('Access denied.');

call_user_func(function ($extensionKey): void {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['transactor_include']['paypal'] =  \JambageCom\TransactorPaypal\Controller\ServerResponseController::class . '::processRequest';

    $excludedParameters = [
        'amt',
        'cc',
        'cm',
        'st',
        'tx'
    ];
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] =
        array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'], $excludedParameters);

}, 'transactor_paypal');
