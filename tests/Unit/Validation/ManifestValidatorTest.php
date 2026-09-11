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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Validation\ComposerJsonValidationError;
use TYPO3\Tailor\Validation\EmConfValidationError;
use TYPO3\Tailor\Validation\ManifestValidator;

class ManifestValidatorTest extends TestCase
{
    private const COMPOSER = __DIR__ . '/../Fixtures/ComposerJson/';
    private const EMCONF = __DIR__ . '/../Fixtures/EmConf/';

    #[Test]
    public function noManifestAtAllIsReported(): void
    {
        $errors = (new ManifestValidator(null, null))->collectErrors('1.0.0');

        self::assertCount(1, $errors);
        self::assertStringContainsString('Neither', $errors[0]);
        self::assertStringContainsString('composer.json', $errors[0]);
        self::assertStringContainsString('ext_emconf.php', $errors[0]);
    }

    #[Test]
    public function emConfAloneIsValidatedAsBefore(): void
    {
        $subject = new ManifestValidator(null, self::EMCONF . 'emconf_valid.php');

        self::assertSame([], $subject->collectErrors('1.0.0'));
        self::assertSame(
            [EmConfValidationError::EXTENSION_VERSION_MISMATCH->getErrorMessage()],
            $subject->collectErrors('9.9.9')
        );
    }

    #[Test]
    public function composerJsonAloneIsEnough(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_valid.json', null);

        self::assertSame([], $subject->collectErrors('1.0.0'));
    }

    #[Test]
    public function composerJsonAloneMustDeclareTheCoreConstraint(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_no_require.json', null);

        self::assertSame(
            [ComposerJsonValidationError::MISSING_TYPO3_VERSION_CONSTRAINT->getErrorMessage()],
            $subject->collectErrors('1.0.0')
        );
    }

    #[Test]
    public function aBrokenComposerJsonIsRejectedDespiteAValidEmConf(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_invalid.json', self::EMCONF . 'emconf_valid.php');

        self::assertSame(
            [ComposerJsonValidationError::UNREADABLE->getErrorMessage()],
            $subject->collectErrors('1.0.0')
        );
    }

    #[Test]
    public function theEmConfConstraintCountsWhenComposerJsonDeclaresNoRequire(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_no_require.json', self::EMCONF . 'emconf_valid.php');

        self::assertSame([], $subject->collectErrors('1.0.0'));
    }

    #[Test]
    public function theEmConfVersionCountsWhenComposerJsonDeclaresNone(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_valid.json', self::EMCONF . 'emconf_valid.php');

        self::assertSame([], $subject->collectErrors('1.0.0'));
        self::assertSame(
            [EmConfValidationError::EXTENSION_VERSION_MISMATCH->getErrorMessage()],
            $subject->collectErrors('9.9.9')
        );
    }

    #[Test]
    public function theComposerVersionWinsOverTheEmConfVersion(): void
    {
        // composer.json says 1.2.3, ext_emconf.php says 1.0.0: TER takes composer.json
        $subject = new ManifestValidator(self::COMPOSER . 'composer_with_version.json', self::EMCONF . 'emconf_valid.php');

        self::assertSame([], $subject->collectErrors('1.2.3'));
        self::assertSame(
            [ComposerJsonValidationError::EXTENSION_VERSION_MISMATCH->getErrorMessage()],
            $subject->collectErrors('1.0.0')
        );
    }

    #[Test]
    public function anEmConfWithoutAVersionIsFineNextToAComposerJson(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_valid.json', self::EMCONF . 'emconf_no_version.php');

        self::assertSame([], $subject->collectErrors('1.0.0'));
    }

    #[Test]
    public function anUnreadableEmConfIsStillReportedNextToAComposerJson(): void
    {
        $subject = new ManifestValidator(self::COMPOSER . 'composer_valid.json', self::EMCONF . 'emconf_no_structure.php');

        self::assertSame(
            [EmConfValidationError::UNSUPPORTED_TYPE->getErrorMessage()],
            $subject->collectErrors('1.0.0')
        );
    }
}
