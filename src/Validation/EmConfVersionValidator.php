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
 * Check if the version in ext_emconf matches the given version
 * and a proper TYPO3 dependency is included.
 */
class EmConfVersionValidator
{
    /**
     * @var string
     */
    protected $emConfFilePath;

    /**
     * @param string $filePath Full path to the ext_emconf.php file
     */
    public function __construct(string $filePath)
    {
        $this->emConfFilePath = $filePath;
    }

    /**
     * @param string $givenVersion
     * @return bool TRUE if the ext_emconf is valid, FALSE otherwise
     */
    public function isValid(string $givenVersion): bool
    {
        $_EXTKEY = 'dummy';
        @include $this->emConfFilePath;
        if (!isset($EM_CONF)) {
            return false;
        }
        $emConfDetails = reset($EM_CONF);
        if (!is_array($emConfDetails)) {
            return false;
        }
        if (!isset($emConfDetails['version'], $emConfDetails['constraints']['depends']['typo3'])) {
            return false;
        }
        if ((string)$emConfDetails['version'] !== $givenVersion) {
            return false;
        }
        return true;
    }
}
