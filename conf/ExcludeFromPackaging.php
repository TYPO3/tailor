<?php

// You can also add your custom configuration. Just add the path to your custom
// configuration file to the environment variable `TYPO3_EXCLUDE_FROM_PACKAGING`
// and make sure the file returns an array with the keys `directories` and `files`
// on root level.

// Note: The filter is case insensitive. There is furthermore no need to define the
// filenames with / without a leading dot. This is taken into account automatically.

return [
    'directories' => [
        'bin',
        'build',
        '.ddev',
        '.git',
        '.github',
        '.gitlab-ci',
        '.gitlab',
        '.idea',
        'tailor-version-upload',
        'tests',
        'vendor'
    ],
    'files' => [
        'bower.json',
        'codeception.yml',
        'composer.lock',
        'crowdin.yaml',
        'docker-compose.yml',
        'Dockerfile',
        'DS_Store',
        'dynamicReturnTypeMeta.json',
        'editorconfig',
        'env',
        'ExtensionBuilder.json',
        'gitattributes',
        'gitignore',
        'gitlab-ci.yml',
        'gitmodules',
        'gitreview',
        'Makefile',
        'package-lock.json',
        'package.json',
        'php_cs.dist',
        'phplint.yml',
        'phpstan.neon',
        'phpunit.xml',
        'scrutinizer.yml',
        'styleci.yml',
        'stylelintrc',
        'travis.yml',
        'webpack.config.js',
        'webpack.mix.js',
        'yarn.lock'
    ]
];
