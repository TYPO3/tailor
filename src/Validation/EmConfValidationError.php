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
 * Validation errors of ext_emconf.php files.
 */
enum EmConfValidationError: string
{
    case EXTENSION_VERSION_MISMATCH = 'extension-version-mismatch';
    case MISSING_CONFIGURATION = 'missing-configuration';
    case MISSING_EXTENSION_VERSION = 'missing-version';
    case MISSING_TYPO3_VERSION_CONSTRAINT = 'missing-typo3-version-constraint';
    case NOT_FOUND = 'not-found';
    case UNSUPPORTED_TYPE = 'unsupported-type';

    public function getErrorMessage(): string
    {
        return match ($this) {
            self::EXTENSION_VERSION_MISMATCH => 'The configured version in `ext_emconf.php` file does not match the given version for release.',
            self::MISSING_CONFIGURATION => 'The `ext_emconf.php` file is missing an $EM_CONF configuration array.',
            self::MISSING_EXTENSION_VERSION => 'No version configured in `ext_emconf.php` file.',
            self::MISSING_TYPO3_VERSION_CONSTRAINT => 'No TYPO3 version constraint configured in `ext_emconf.php` file.',
            self::NOT_FOUND => 'No `ext_emconf.php` file found in the folder.',
            self::UNSUPPORTED_TYPE => 'The $EM_CONF variable in `ext_emconf.php` file contains an unsupported type (should be an array).',
        };
    }
}
