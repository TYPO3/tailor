<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Validation\VersionValidator;

class VersionValidatorTest extends TestCase
{
    /**
     * @test
     * @dataProvider isValidTestDataProvider
     *
     * @param string $input
     * @param bool $expected
     */
    public function isValidTest(string $input, bool $expected): void
    {
        self::assertEquals($expected, (new VersionValidator())->isValid($input));
    }

    /**
     * Data provider for isValidTest
     *
     * @return \Generator
     */
    public function isValidTestDataProvider(): \Generator
    {
        yield 'Wrong format' => [
            'v1',
            false,
        ];
        yield 'Wrong delimiter' => [
            '1-0-0',
            false,
        ];
        yield 'Missing patch version' => [
            '1.0',
            false,
        ];
        yield 'Patch version to high' => [
            '1.0.1000',
            false,
        ];
        yield 'Patch version to low' => [
            '1.0.-12',
            false,
        ];
        yield 'Not numeric' => [
            '0.2.0-alpha',
            false,
        ];
        yield 'Valid version' => [
            '1.0.0',
            true,
        ];
        yield 'Valid version 2' => [
            '10.4.999',
            true,
        ];
    }
}
