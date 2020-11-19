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
 * Checks if the version is of format x.y.z with all digits in it.
 */
class VersionValidator
{
    /**
     * @param string $givenVersion
     * @return bool TRUE if the version is valid, FALSE otherwise
     */
    public function isValid(string $givenVersion): bool
    {
        $versionParts = explode('.', $givenVersion);
        if (count($versionParts) !== 3) {
            return false;
        }
        foreach ($versionParts as $versionPart) {
            if (!is_numeric($versionPart)) {
                return false;
            }
            $versionPart = (int)$versionPart;
            if ($versionPart < 0 || $versionPart > 999) {
                return false;
            }
        }
        return true;
    }
}
