<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Extension;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Filesystem\VersionReplacer;
use TYPO3\Tailor\Validation\VersionValidator;

/**
 * Command for updating the extension version in ext_emconf.php and
 * the extension documentation configuration file Settings.cfg.
 */
class SetExtensionVersionCommand extends Command
{
    private const EMCONF_PATTERN = '["\']version["\']\s=>\s["\']((?:[0-9]+)\.[0-9]+\.[0-9]+\s*)["\']';
    private const DOCUMENTATION_VERSION_PATTERN = 'version\s*=\s*([0-9]+\.[0-9]+)';
    private const DOCUMENTATION_RELEASE_PATTERN = 'release\s*=\s*([0-9]+\.[0-9]+\.[0-9]+)';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Update the extensions ext_emconf.php version to a specific version. Useful in CI environments')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to publish, e.g. 1.2.3. Must have three digits.')
            ->addOption('path', '', InputOption::VALUE_OPTIONAL, 'Path to the extension folder', getcwd() ?: './')
            ->addOption('no-docs', '', InputOption::VALUE_OPTIONAL, 'Disable version update in documentation settings', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $version = (string)$input->getArgument('version');

        if (!(new VersionValidator())->isValid($version)) {
            $io->error(sprintf('The given version "%s" must contain three digits in the format "1.2.3".', $version));
            return 1;
        }

        $path = realpath($input->getOption('path'));
        if (!$path) {
            $io->error(sprintf('Given path %s does not exist.', $path));
            return 1;
        }

        $emConfFile = rtrim($path, '/') . '/ext_emconf.php';
        if (!file_exists($emConfFile)) {
            $io->error(sprintf('No \'ext_emconf.php\' found in the given path %s.', $path));
            return 1;
        }

        $versionReplacer = new VersionReplacer($version);

        try {
            $versionReplacer->setVersion($emConfFile, self::EMCONF_PATTERN);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('An error occurred while setting the ext_emconf.php version to %s.', $version));
            return 1;
        }

        if ($input->getOption('no-docs') === null
            || (bool)$input->getOption('no-docs') === true
            || Variables::has('TYPO3_DISABLE_DOCS_VERSION_UPDATE')
        ) {
            return 0;
        }

        $documentationSettingsFile = rtrim($path, '/') . '/Documentation/Settings.cfg';
        if (!file_exists($documentationSettingsFile)) {
            $io->note(
                'Documentation version update is enabled but was not performed because the file '
                . $documentationSettingsFile . ' does not exist. To disable this operation use the \'--no-docs\' '
                . 'option or set the \'TYPO3_DISABLE_DOCS_VERSION_UPDATE\' environment variable.'
            );
            return 0;
        }

        try {
            $versionReplacer->setVersion($documentationSettingsFile, self::DOCUMENTATION_RELEASE_PATTERN);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('An error occurred while updating the release number in %s', $documentationSettingsFile));
            return 1;
        }

        try {
            $versionReplacer->setVersion($documentationSettingsFile, self::DOCUMENTATION_VERSION_PATTERN, 2);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('An error occurred while updating the version number in %s', $documentationSettingsFile));
            return 1;
        }

        return 0;
    }
}
