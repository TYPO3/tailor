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

    /**
     * @test
     */
    public function getExtensionKeyFromInputThrowsExceptionIfInputHasNoArgumentDefined(): void
    {
        $this->expectException(ExtensionKeyMissingException::class);
        $this->expectExceptionMessage('The extension key must either be set as argument, as environment variable or in the composer.json.');
        $this->expectExceptionCode(1605706548);

        CommandHelper::getExtensionKeyFromInput($this->input);
    }

    /**
     * @test
     */
    public function getExtensionKeyFromInputReturnsExtensionKeyFromInputArgument(): void
    {
        $this->definition->addArgument(new InputArgument('extensionkey', InputArgument::REQUIRED));
        $this->input->setArgument('extensionkey', 'foo');

        self::assertSame('foo', CommandHelper::getExtensionKeyFromInput($this->input));
    }

    /**
     * @test
     */
    public function getExtensionKeyFromInputIgnoresEmptyInputArgumentValue(): void
    {
        $this->expectException(ExtensionKeyMissingException::class);
        $this->expectExceptionMessage('The extension key must either be set as argument, as environment variable or in the composer.json.');
        $this->expectExceptionCode(1605706548);

        $this->definition->addArgument(new InputArgument('extensionkey', InputArgument::OPTIONAL));
        $this->input->setArgument('extensionkey', '');

        CommandHelper::getExtensionKeyFromInput($this->input);
    }

    /**
     * @test
     */
    public function getExtensionKeyFromInputReturnsExtensionKeyFromEnvironmentVariables(): void
    {
        putenv('TYPO3_EXTENSION_KEY=foo');

        self::assertSame('foo', CommandHelper::getExtensionKeyFromInput($this->input));

        putenv('TYPO3_EXTENSION_KEY');
    }
}
