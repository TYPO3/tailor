<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Environment;

/**
 * Check and access environment variables
 */
class Variables
{
    public static function has(string $name, bool $allowEmpty = false): bool
    {
        $value = $_ENV[$name] ?? getenv($name);

        return $allowEmpty ? is_string($value) : (bool)$value;
    }

    public static function get(string $name): string
    {
        return $_ENV[$name] ?? getenv($name) ?: '';
    }
}
