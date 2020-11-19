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
use TYPO3\Tailor\Filesystem\EmConfVersionReplacer;
use TYPO3\Tailor\Validation\VersionValidator;

/**
 * Command for changing the ext_emconf.php to set the "version" property to a specific version.
 */
class SetExtensionVersionCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Update the extensions ext_emconf.php version to a specific version. Useful in CI environments')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to publish, e.g. 1.2.3. Must have three digits.')
            ->addOption('path', '', InputOption::VALUE_OPTIONAL, 'Path to the extension folder', getcwd() ?: './');
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

        try {
            (new EmConfVersionReplacer($emConfFile))->setVersion($version);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('An error occurred while setting the ext_emconf.php version to %s.', $version));
            return 1;
        }

        return 0;
    }
}
