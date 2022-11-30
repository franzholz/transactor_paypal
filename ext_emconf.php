<?php

/***************************************************************
* Extension Manager/Repository config file for ext "transactor_paypal".
***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Transactor PayPal Gateway',
    'description' => 'Provides the possibility to transact payments via PayPal using the Payment Transactor extension.',
    'category' => 'misc',
    'author' => 'Franz Holzinger',
    'author_email' => 'franz@ttproducts.de',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author_company' => 'jambage.com',
    'version' => '0.3.2',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-8.1.99',
            'typo3' => '9.5.0-11.5.99',
            'transactor' => '0.9.0-0.0.0'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];

