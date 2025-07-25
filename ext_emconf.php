<?php

/***************************************************************
* Extension Manager/Repository config file for ext "transactor_paypal".
***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'PayPal Payments Standard',
    'description' => 'Provides the possibility to transact payments via PayPal based on Webpayment Standard using the Payment Transactor extension.',
    'category' => 'misc',
    'author' => 'Franz Holzinger',
    'author_email' => 'franz@ttproducts.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'author_company' => 'jambage.com',
    'version' => '0.6.1',
    'constraints' => [
        'depends' => [
            'php' => '8.0.0-8.4.99',
            'typo3' => '12.4.0-13.4.99',
            'transactor' => '0.12.0-0.0.0'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];

