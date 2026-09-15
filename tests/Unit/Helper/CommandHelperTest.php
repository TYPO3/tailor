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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TYPO3\Tailor\Dto\ResolvedVersion;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Exception\VersionMissingException;
use TYPO3\Tailor\Helper\CommandHelper;
use TYPO3\Tailor\Tests\Unit\GitRepositoryTrait;

final class CommandHelperTest extends TestCase
{
    use GitRepositoryTrait;

    /**
     * @var InputDefinition
     */
    private $definition;

    /**
     * @var ArrayInput
     */
    private $input;

    /**
     * @var string
     */
    private $extensionPath = '';

    protected function setUp(): void
    {
        $this->definition = new InputDefinition();
        $this->input = new ArrayInput([], $this->definition);
        $this->extensionPath = sys_get_temp_dir() . '/tailor-helper-' . bin2hex(random_bytes(6));
        mkdir($this->extensionPath, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up environment variable after each test
        putenv('TYPO3_EXTENSION_KEY');
        $this->removeExtensionPath();
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

    #[Test]
    public function getVersionFromInputPrefersTheArgumentOverEveryOtherSource(): void
    {
        $this->writeEmConf('1.2.3');
        $this->createGitRepository($this->extensionPath, '2.0.0');

        $version = CommandHelper::getVersionFromInput($this->versionInput(['version' => '3.0.0']));

        self::assertSame('3.0.0', $version->getVersion());
        self::assertSame(ResolvedVersion::SOURCE_ARGUMENT, $version->getSource());
        self::assertTrue($version->isFromArgument());
    }

    #[Test]
    public function getVersionFromInputPrefersTheTagOverTheVersionFiles(): void
    {
        $this->writeEmConf('1.2.3');
        $this->writeComposerJson('1.2.3');
        $this->createGitRepository($this->extensionPath, '2.0.0');

        $version = CommandHelper::getVersionFromInput($this->versionInput());

        self::assertSame('2.0.0', $version->getVersion());
        self::assertSame(ResolvedVersion::SOURCE_GIT_TAG, $version->getSource());
    }

    #[Test]
    public function getVersionFromInputReturnsEmConfVersionForUntaggedCommits(): void
    {
        $this->writeEmConf('1.2.3');
        $this->writeComposerJson('2.0.0');
        $this->createGitRepository($this->extensionPath);

        $version = CommandHelper::getVersionFromInput($this->versionInput());

        self::assertSame('1.2.3', $version->getVersion());
        self::assertSame(ResolvedVersion::SOURCE_EMCONF, $version->getSource());
    }

    #[Test]
    public function getVersionFromInputFallsBackToComposerJson(): void
    {
        $this->writeEmConf(null);
        $this->writeComposerJson('2.0.0');

        $version = CommandHelper::getVersionFromInput($this->versionInput());

        self::assertSame('2.0.0', $version->getVersion());
        self::assertSame(ResolvedVersion::SOURCE_COMPOSER, $version->getSource());
    }

    #[Test]
    public function getVersionFromInputTakesTheTypo3SectionOfComposerJsonIntoAccount(): void
    {
        file_put_contents($this->extensionPath . '/composer.json', (string)json_encode([
            'name' => 'vendor/my-ext',
            'extra' => ['typo3/cms' => ['extension-key' => 'my_ext', 'version' => '2.0.0']],
        ]));

        self::assertSame('2.0.0', CommandHelper::getVersionFromInput($this->versionInput())->getVersion());
    }

    #[Test]
    public function getVersionFromInputIgnoresValuesWhichAreNoVersion(): void
    {
        $this->writeEmConf('dev-main');
        $this->writeComposerJson('2.0.0');

        self::assertSame('2.0.0', CommandHelper::getVersionFromInput($this->versionInput())->getVersion());
    }

    #[Test]
    public function getVersionFromInputThrowsExceptionIfNoVersionCanBeDetermined(): void
    {
        $this->writeEmConf(null);
        $this->writeComposerJson(null);

        $this->expectException(VersionMissingException::class);
        $this->expectExceptionCode(1786492802);

        CommandHelper::getVersionFromInput($this->versionInput());
    }

    #[Test]
    public function getVersionFromInputThrowsExceptionIfTheCommitIsTaggedWithSeveralVersions(): void
    {
        $this->writeEmConf('1.2.3');
        $this->createGitRepository($this->extensionPath, '2.0.0', '2.0.1');

        $this->expectException(VersionMissingException::class);
        $this->expectExceptionCode(1786492801);

        CommandHelper::getVersionFromInput($this->versionInput());
    }

    #[Test]
    public function normalizeVersionAndExtensionKeyArgumentsMovesASoleExtensionKeyToItsArgument(): void
    {
        $input = $this->versionInput(['version' => 'my_ext']);
        CommandHelper::normalizeVersionAndExtensionKeyArguments($input);

        self::assertNull($input->getArgument('version'));
        self::assertSame('my_ext', $input->getArgument('extensionkey'));
    }

    #[Test]
    public function normalizeVersionAndExtensionKeyArgumentsKeepsGivenArguments(): void
    {
        $input = $this->versionInput(['version' => '1.2.3', 'extensionkey' => 'my_ext']);
        CommandHelper::normalizeVersionAndExtensionKeyArguments($input);

        self::assertSame('1.2.3', $input->getArgument('version'));
        self::assertSame('my_ext', $input->getArgument('extensionkey'));
    }

    #[Test]
    public function getPathFromInputFallsBackToTheCurrentWorkingDirectory(): void
    {
        self::assertSame((string)getcwd(), CommandHelper::getPathFromInput($this->input));
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

    /**
     * @param array<string, string> $parameters
     */
    private function versionInput(array $parameters = []): InputInterface
    {
        return new ArrayInput($parameters + ['--path' => $this->extensionPath], new InputDefinition([
            new InputArgument('version', InputArgument::OPTIONAL),
            new InputArgument('extensionkey', InputArgument::OPTIONAL),
            new InputOption('path', '', InputOption::VALUE_OPTIONAL),
        ]));
    }

    private function writeEmConf(?string $version): void
    {
        $version = $version === null ? '' : sprintf("    'version' => '%s',\n", $version);

        file_put_contents($this->extensionPath . '/ext_emconf.php', <<<PHP
            <?php

            \$EM_CONF[\$_EXTKEY] = [
                'title' => 'My extension',
            {$version}];
            PHP);
    }

    private function writeComposerJson(?string $version): void
    {
        $composerSchema = ['name' => 'vendor/my-ext', 'type' => 'typo3-cms-extension'];

        if ($version !== null) {
            $composerSchema['version'] = $version;
        }

        file_put_contents($this->extensionPath . '/composer.json', (string)json_encode($composerSchema));
    }

    private function removeExtensionPath(): void
    {
        if ($this->extensionPath === '' || !is_dir($this->extensionPath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->extensionPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->extensionPath);
        $this->extensionPath = '';
    }

}
