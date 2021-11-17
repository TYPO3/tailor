<?php

$EM_CONF['my_extension'] = [
    'title' => 'My extension',
    'description' => 'Great extension - everyone needs it',
    'category' => 'be',
    'author' => 'John Doe',
    'author_email' => 'john@acme.com',
    'state' => 'stable',
    'uploadfolder' => 0,
    'clearCacheOnLoad' => 1,
    'author_company' => 'ACME Corporation',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.0.0-11.99.99',
        ],
    ],
];
