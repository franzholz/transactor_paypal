<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function () {
    if (
        version_compare(TYPO3_version, '9.5.0', '<')
    ) {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['paypal'] = 'EXT:transactor_paypal/Resources/Public/Scripts/Php/EidRunner.php';
    } else if (
        version_compare(TYPO3_version, '9.5', '==')
    ) {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['transactor_include']['paypal'] =  \JambageCom\TransactorPaypal\Controller\OldServerResponseController::class . '::processRequest';
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['transactor_include']['paypal'] =  \JambageCom\TransactorPaypal\Controller\ServerResponseController::class . '::processRequest';
    }
    
    $excludedParameters = [
        'amt',
        'cc',
        'cm',
        'st',
        'tx'
    ];
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = 
        array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'], $excludedParameters);

});

