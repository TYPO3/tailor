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
use TYPO3\Tailor\Validation\EmConfValidationError;
use TYPO3\Tailor\Validation\EmConfVersionValidator;

class EmConfVersionValidatorTest extends TestCase
{
    /**
     * @test
     */
    public function collectErrorsReturnsErrorIfFileDoesNotExist(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/no-file');
        $expected = [EmConfValidationError::NOT_FOUND];
        self::assertSame($expected, $subject->collectErrors('1.2.0'));
    }

    /**
     * @test
     */
    public function collectErrorsReturnsErrorIfConfigurationIsMissing(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_invalid.php');
        $expected = [EmConfValidationError::MISSING_CONFIGURATION];
        self::assertSame($expected, $subject->collectErrors('1.0.0'));
    }

    /**
     * @test
     */
    public function collectErrorsReturnsErrorIfFileDoesNotMatchEmConfStructure(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_no_structure.php');
        $expected = [EmConfValidationError::UNSUPPORTED_TYPE];
        self::assertSame($expected, $subject->collectErrors('1.0.0'));
    }

    /**
     * @test
     */
    public function collectErrorsReturnsErrorsIfNoVersionGiven(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_no_version.php');
        $expected = [
            EmConfValidationError::MISSING_EXTENSION_VERSION,
            EmConfValidationError::MISSING_TYPO3_VERSION_CONSTRAINT,
        ];
        self::assertSame($expected, $subject->collectErrors('1.0.0'));
    }

    /**
     * @test
     */
    public function collectErrorsReturnsErrorIfVersionsDoNotMatch(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_valid.php');
        $expected = [EmConfValidationError::EXTENSION_VERSION_MISMATCH];
        self::assertSame($expected, $subject->collectErrors('2.0.0'));
    }

    /**
     * @test
     */
    public function collectErrorsReturnsEmptyArrayIfFileIsValid(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_valid.php');
        self::assertSame([], $subject->collectErrors('1.0.0'));
    }

    /**
     * @test
     */
    public function isInvalidIfNoFileFound(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/no-file');
        self::assertFalse($subject->isValid('1.2.0'));
    }

    /**
     * @test
     */
    public function isInvalidIfFileDoesNotMatchEmConfStructure(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_invalid.php');
        self::assertFalse($subject->isValid('1.0.0'));
    }

    /**
     * @test
     */
    public function isInvalidIfNoVersionGiven(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_no_version.php');
        self::assertFalse($subject->isValid('1.0.0'));
    }

    /**
     * @test
     */
    public function isValidMatchesVersion(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_valid.php');
        self::assertFalse($subject->isValid('1.2.0'));
        self::assertTrue($subject->isValid('1.0.0'));
    }

    /**
     * @test
     */
    public function isValidWithStringArrayKey(): void
    {
        $subject = new EmConfVersionValidator(__DIR__ . '/../Fixtures/EmConf/emconf_valid_string_array_key.php');
        self::assertTrue($subject->isValid('1.0.0'));
    }
}
