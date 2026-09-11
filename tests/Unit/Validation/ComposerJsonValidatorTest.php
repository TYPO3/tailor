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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Validation\ComposerJsonValidationError;
use TYPO3\Tailor\Validation\ComposerJsonValidator;

class ComposerJsonValidatorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/ComposerJson/';

    #[Test]
    public function collectErrorsReturnsErrorIfFileDoesNotExist(): void
    {
        $subject = new ComposerJsonValidator(__DIR__ . '/no-file');
        self::assertSame([ComposerJsonValidationError::NOT_FOUND], $subject->collectErrors('1.2.3'));
    }

    #[Test]
    #[DataProvider('unreadableFileDataProvider')]
    public function collectErrorsReturnsErrorIfFileCannotBeRead(string $fixture): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . $fixture);
        self::assertSame([ComposerJsonValidationError::UNREADABLE], $subject->collectErrors('1.2.3'));
    }

    public static function unreadableFileDataProvider(): array
    {
        return [
            'invalid json' => ['composer_invalid.json'],
            'not an object' => ['composer_not_an_object.json'],
        ];
    }

    #[Test]
    public function collectErrorsReturnsErrorIfTypeIsNotAnExtension(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_library.json');
        self::assertSame([ComposerJsonValidationError::NOT_AN_EXTENSION], $subject->collectErrors('1.2.3'));
    }

    #[Test]
    public function collectErrorsReturnsNothingForAValidManifestWithoutAVersion(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_valid.json');
        self::assertSame([], $subject->collectErrors('1.2.3'));
        self::assertTrue($subject->isValid('9.9.9'));
    }

    #[Test]
    public function collectErrorsAcceptsAMatchingVersion(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_with_version.json');
        self::assertSame([], $subject->collectErrors('1.2.3'));
    }

    #[Test]
    public function collectErrorsReturnsErrorIfVersionsDoNotMatch(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_with_version.json');
        self::assertSame([ComposerJsonValidationError::EXTENSION_VERSION_MISMATCH], $subject->collectErrors('2.0.0'));
    }

    #[Test]
    public function collectErrorsPrefersTheTypo3VersionOverTheComposerVersion(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_with_typo3_version.json');
        self::assertSame([], $subject->collectErrors('1.2.3'));
        self::assertSame([ComposerJsonValidationError::EXTENSION_VERSION_MISMATCH], $subject->collectErrors('9.9.9'));
    }

    #[Test]
    public function collectErrorsReturnsErrorIfRequireLacksTheCore(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_no_core.json');
        self::assertSame([ComposerJsonValidationError::MISSING_TYPO3_VERSION_CONSTRAINT], $subject->collectErrors('1.2.3'));
    }

    #[Test]
    public function collectErrorsReturnsErrorIfNothingDeclaresTheCoreConstraint(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_no_require.json');
        self::assertSame([ComposerJsonValidationError::MISSING_TYPO3_VERSION_CONSTRAINT], $subject->collectErrors('1.2.3'));
    }

    #[Test]
    public function collectErrorsAcceptsAMissingRequireWhenTheEmConfDeclaresTheConstraint(): void
    {
        $subject = new ComposerJsonValidator(self::FIXTURES . 'composer_no_require.json');
        self::assertSame([], $subject->collectErrors('1.2.3', true));
    }

    #[Test]
    public function declaresVersionTellsWhetherTheManifestCarriesOne(): void
    {
        self::assertFalse((new ComposerJsonValidator(self::FIXTURES . 'composer_valid.json'))->declaresVersion());
        self::assertTrue((new ComposerJsonValidator(self::FIXTURES . 'composer_with_version.json'))->declaresVersion());
        self::assertFalse((new ComposerJsonValidator(self::FIXTURES . 'composer_invalid.json'))->declaresVersion());
    }

    #[Test]
    public function declaresRequireTellsWhetherTheManifestCarriesConstraints(): void
    {
        self::assertTrue((new ComposerJsonValidator(self::FIXTURES . 'composer_no_core.json'))->declaresRequire());
        self::assertFalse((new ComposerJsonValidator(self::FIXTURES . 'composer_no_require.json'))->declaresRequire());
    }

    #[Test]
    public function everyErrorHasItsOwnMessage(): void
    {
        $messages = array_map(
            static fn(ComposerJsonValidationError $error): string => $error->getErrorMessage(),
            ComposerJsonValidationError::cases()
        );

        self::assertCount(count(ComposerJsonValidationError::cases()), array_unique($messages));
        self::assertNotContains('', $messages);
    }
}
