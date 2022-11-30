<?php

defined('TYPO3_MODE') || die('Access denied.');

/** @var \JambageCom\TransactorPaypal\Eid\Listener $eid */
$eid = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\JambageCom\TransactorPaypal\Eid\Listener::class);
echo $eid->run();

