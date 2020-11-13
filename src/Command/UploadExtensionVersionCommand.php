<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\ServerException;
use TYPO3\Tailor\HttpClientFactory;

/**
 * Uploads a .zip file to TER
 */
class UploadExtensionVersionCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Publishes a new version of an extension to TER')
            ->addArgument(
                'extensionkey',
                InputArgument::REQUIRED,
                'The extension key'
            )
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'The version to publish, e.g. 1.2.3'
            )
            ->addOption(
                'path',
                '',
                InputOption::VALUE_REQUIRED,
                'Path to the extension folder'
            )
            ->addOption(
                'artefact',
                '',
                InputOption::VALUE_REQUIRED,
                'Path or URL to a zip file'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Uploading an extension');

        return 0;
    }
}