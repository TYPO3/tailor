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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\ServerException;
use TYPO3\Tailor\HttpClientFactory;

/**
 * Queries /api/v1/extension
 */
class FindExtensionsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Fetch a list of extensions from TER')
            ->addOption(
                'author',
                '',
                InputOption::VALUE_REQUIRED,
                'Filter by a specific author. Use the TYPO3 username, e.g. georgringer'
            )
            ->addOption(
                'typo3-version',
                '',
                InputOption::VALUE_REQUIRED,
                'Only list extensions compatible with a specific major TYPO3 version, use it like --typo3-version=10'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Find registered remote extensions');
        try {
            $client = HttpClientFactory::create('v1');
            $page = 1;
            $filterParams = '';
            $limitToUsername = (string)$input->getOption('author');
            if (!empty($limitToUsername)) {
                $filterParams = '&filter[username]=' . rawurlencode($limitToUsername);
            }
            $limitToCoreVersion = (int)$input->getOption('typo3-version');
            if ($limitToCoreVersion) {
                $filterParams = '&filter[typo3_version]=' . $limitToCoreVersion;
            }
            $extensions = [];
            do {
                $response = $client->request('GET', 'extension?per_page=50' . $filterParams . '&page=' . $page++);
                $content = json_decode($response->getContent(), true);
                foreach ($content['extensions'] as $extensionData) {
                    $extensions[$extensionData['key']] = [
                        $extensionData['key'],
                        $extensionData['current_version']['title'] ?? '-',
                        $extensionData['current_version']['number'] ?? '-',
                        isset($extensionData['current_version']['upload_date']) ? date('d.m.Y', $extensionData['current_version']['upload_date']) : '-',
                        $extensionData['meta']['composer_name'] ?? '-',
                    ];
                }
                $page++;
                // Hard limit on max 500 items
            } while (!empty($content['extensions']) && $page < 10);
            ksort($extensions);
            $io->table(
                ['Extension Key', 'Title', 'Latest Version', 'Last Updated on', 'Composer Name'],
                $extensions
            );
        } catch (ServerException $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
        return 0;
    }
}