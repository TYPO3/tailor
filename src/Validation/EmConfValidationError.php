<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Validation;

/**
 * Enum with validation errors of ext_emconf.php files.
 *
 * @todo Convert to native enum once support for PHP < 8.1 is dropped.
 */
abstract class EmConfValidationError
{
    public const EXTENSION_VERSION_MISMATCH = 'extension-version-mismatch';
    public const MISSING_CONFIGURATION = 'missing-configuration';
    public const MISSING_EXTENSION_VERSION = 'missing-version';
    public const MISSING_TYPO3_VERSION_CONSTRAINT = 'missing-typo3-version-constraint';
    public const NOT_FOUND = 'not-found';
    public const UNSUPPORTED_TYPE = 'unsupported-type';

    public static function getErrorMessage(string $error): string
    {
        switch ($error) {
            case self::EXTENSION_VERSION_MISMATCH:
                return 'The configured version in `ext_emconf.php` file does not match the given version for release.';
            case self::MISSING_CONFIGURATION:
                return 'The `ext_emconf.php` file is missing an $EM_CONF configuration array.';
            case self::MISSING_EXTENSION_VERSION:
                return 'No version configured in `ext_emconf.php` file.';
            case self::MISSING_TYPO3_VERSION_CONSTRAINT:
                return 'No TYPO3 version constraint configured in `ext_emconf.php` file.';
            case self::NOT_FOUND:
                return 'No `ext_emconf.php` file found in the folder.';
            case self::UNSUPPORTED_TYPE:
                return 'The $EM_CONF variable in `ext_emconf.php` file contains an unsupported type (should be an array).';
            default:
                // @todo Can be removed once this class is converted to a native enum.
                return 'An unexpected error occurred while reading the `ext_emconf.php` file.';
        }
    }
}
