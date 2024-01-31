<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project - inspiring people to share!
 * (c) 2020-2023 Oliver Bartsch, Benni Mack & Elias Häußler
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Extension;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Command\AbstractCommand;
use TYPO3\Tailor\Filesystem;
use TYPO3\Tailor\Service\VersionService;

/**
 * Command to create a local extension artefact (zip archive).
 */
class CreateExtensionArtefactCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setDescription('Create an artefact file (zip archive) of an extension');

        $this->addArgument(
            'version',
            InputArgument::REQUIRED,
            'The version of the extension, e.g. 1.2.3'
        );
        $this->addArgument(
            'extensionkey',
            InputArgument::OPTIONAL,
            'The extension key'
        );
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to the extension folder'
        );
        $this->addOption(
            'artefact',
            null,
            InputOption::VALUE_REQUIRED,
            'Path or URL to a zip file'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $version = $input->getArgument('version');
        $extensionKey = $this->getExtensionKey($input);
        $path = $input->getOption('path');
        $artefact = $input->getOption('artefact');
        $transactionPath = rtrim(realpath(getcwd() ?: './'), '/') . '/tailor-version-artefact';

        if (!(new Filesystem\Directory())->create($transactionPath)) {
            throw new \RuntimeException(sprintf('Directory could not be created.'));
        }

        $versionService = new VersionService($version, $extensionKey, $transactionPath);

        if ($path !== null) {
            $versionService->createZipArchiveFromPath($path);
        } elseif ($artefact !== null) {
            $versionService->createZipArchiveFromArtefact($artefact);
        } else {
            // If neither `path` nor `artefact` are defined, we just
            // create the ZipArchive from the current directory.
            $versionService->createZipArchiveFromPath(getcwd() ?: './');
        }

        $io->success(sprintf('Extension artefact successfully generated: %s', $versionService->getVersionFilePath()));

        return 0;
    }
}
