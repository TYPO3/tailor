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
 * Validation errors of composer.json files.
 */
enum ComposerJsonValidationError: string
{
    case EXTENSION_VERSION_MISMATCH = 'extension-version-mismatch';
    case MISSING_TYPO3_VERSION_CONSTRAINT = 'missing-typo3-version-constraint';
    case NOT_AN_EXTENSION = 'not-an-extension';
    case NOT_FOUND = 'not-found';
    case UNREADABLE = 'unreadable';

    public function getErrorMessage(): string
    {
        return match ($this) {
            self::EXTENSION_VERSION_MISMATCH => 'The version declared in `composer.json` does not match the given version for release.',
            self::MISSING_TYPO3_VERSION_CONSTRAINT => 'No TYPO3 version constraint found: `composer.json` does not require `typo3/cms-core`, and no `ext_emconf.php` declares one.',
            self::NOT_AN_EXTENSION => 'The `composer.json` file does not describe a TYPO3 extension. Its "type" must be "typo3-cms-extension".',
            self::NOT_FOUND => 'No `composer.json` file found in the folder.',
            self::UNREADABLE => 'The `composer.json` file could not be read. It must be a valid JSON object.',
        };
    }
}
