<?php

// You can also add your custom configuration. Just add the path to your custom
// configuration file to the environment variable `TYPO3_EXCLUDE_FROM_PACKAGING`
// and make sure the file returns an array with the keys `directories` and `files`
// on root level.

// Note: The filter is case insensitive. There is furthermore no need to define the
// filenames with / without a leading dot. This is taken into account automatically.

return [
    'directories' => [
        '.build',
        '.ddev',
        '.git',
        '.github',
        '.gitlab',
        '.gitlab-ci',
        '.idea',
        'bin',
        'build',
        'tailor-version-upload',
        'tests',
        'vendor',
    ],
    'files' => [
        'DS_Store',
        'Dockerfile',
        'ExtensionBuilder.json',
        'Makefile',
        'bower.json',
        'codeception.yml',
        'composer.lock',
        'crowdin.yaml',
        'docker-compose.yml',
        'dynamicReturnTypeMeta.json',
        'editorconfig',
        'env',
        'gitattributes',
        'gitignore',
        'gitlab-ci.yml',
        'gitmodules',
        'gitreview',
        'package-lock.json',
        'package.json',
        'php_cs',
        'phplint.yml',
        'phpstan.neon',
        'phpunit.xml',
        'scrutinizer.yml',
        'styleci.yml',
        'stylelintrc',
        'travis.yml',
        'webpack.config.js',
        'webpack.mix.js',
        'yarn.lock',
    ],
];
