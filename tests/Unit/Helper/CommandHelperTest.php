<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project - inspiring people to share!
 * (c) 2020-2024 Oliver Bartsch, Benni Mack & Elias Häußler
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Helper\CommandHelper;

final class CommandHelperTest extends TestCase
{
    /**
     * @var InputDefinition
     */
    private $definition;

    /**
     * @var ArrayInput
     */
    private $input;

    protected function setUp(): void
    {
        $this->definition = new InputDefinition();
        $this->input = new ArrayInput([], $this->definition);
    }

    protected function tearDown(): void
    {
        // Clean up environment variable after each test
        putenv('TYPO3_EXTENSION_KEY');
    }

    #[Test]
    public function getExtensionKeyFromInputThrowsExceptionIfInputHasNoArgumentDefined(): void
    {
        $this->expectException(ExtensionKeyMissingException::class);
        $this->expectExceptionMessage('The extension key must either be set as argument or in composer.json at [extra][typo3/cms][extension-key].');
        $this->expectExceptionCode(1605706548);

        CommandHelper::getExtensionKeyFromInput($this->input);
    }

    #[Test]
    public function getExtensionKeyFromInputReturnsExtensionKeyFromInputArgument(): void
    {
        $this->definition->addArgument(new InputArgument('extensionkey', InputArgument::REQUIRED));
        $this->input->setArgument('extensionkey', 'foo');

        self::assertSame('foo', CommandHelper::getExtensionKeyFromInput($this->input));
    }

    #[Test]
    public function getExtensionKeyFromInputIgnoresEmptyInputArgumentValue(): void
    {
        $this->expectException(ExtensionKeyMissingException::class);
        $this->expectExceptionMessage('The extension key must either be set as argument or in composer.json at [extra][typo3/cms][extension-key].');
        $this->expectExceptionCode(1605706548);

        $this->definition->addArgument(new InputArgument('extensionkey', InputArgument::OPTIONAL));
        $this->input->setArgument('extensionkey', '');

        CommandHelper::getExtensionKeyFromInput($this->input);
    }

    #[Test]
    public function getExtensionKeyFromInputReturnsExtensionKeyFromEnvironmentVariablesWithDeprecation(): void
    {
        putenv('TYPO3_EXTENSION_KEY=foo');

        $deprecationTriggered = false;
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecationTriggered): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
                self::assertStringContainsString('TYPO3_EXTENSION_KEY environment variable is deprecated', $errstr);
                return true;
            }
            return false;
        });

        try {
            self::assertSame('foo', CommandHelper::getExtensionKeyFromInput($this->input));
            self::assertTrue($deprecationTriggered, 'Expected deprecation warning was not triggered');
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param string[] $expected
     */
    #[Test]
    #[DataProvider('tagsImpliedByTerDataProvider')]
    public function getTagsImpliedByTerReturnsOnlyTermsTerAlreadyImplies(string $tags, array $expected): void
    {
        self::assertSame($expected, CommandHelper::getTagsImpliedByTer($tags));
    }

    /**
     * @return array<string, array{0: string, 1: string[]}>
     */
    public static function tagsImpliedByTerDataProvider(): array
    {
        return [
            'empty input' => ['', []],
            'only domain tags' => ['search,indexing,facets', []],
            'single implied tag' => ['typo3,search', ['typo3']],
            'several implied tags' => ['typo3,php,search,extension', ['typo3', 'php', 'extension']],
            'case is ignored' => ['TYPO3,Extension', ['TYPO3', 'Extension']],
            'surrounding whitespace' => [' typo3 , search ', ['typo3']],
            'empty segments' => ['typo3,,search,', ['typo3']],
            'substring is not a match' => ['typo3-solr,phpunit', []],
            // Spelling variants observed in the wild: composer.json keywords
            // favour "typo3 cms", GitHub topics "typo3-cms-extension".
            'separator variants' => [
                'typo3 cms,typo3-cms,typo3cms',
                ['typo3 cms', 'typo3-cms', 'typo3cms'],
            ],
            'extension variants' => [
                'typo3-cms-extension,typo3-extension,cms-extension',
                ['typo3-cms-extension', 'typo3-extension', 'cms-extension'],
            ],
            'domain tag with separator survives' => ['e-commerce,tt_news', []],
        ];
    }
}
