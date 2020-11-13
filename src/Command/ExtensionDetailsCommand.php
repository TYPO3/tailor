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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\ServerException;
use TYPO3\Tailor\HttpClientFactory;

/**
 * Queries /api/v1/extension/{my-extension-key}
 */
class ExtensionDetailsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Fetch details about an extension')
            ->addArgument(
                'extensionkey',
                InputArgument::REQUIRED,
                'The extension key'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $extensionKey = $input->getArgument('extensionkey');
        $io->title('See all details about extension ' . $extensionKey);
        try {
            $client = HttpClientFactory::create('v1');
            $response = $client->request('GET', 'extension/' . $extensionKey);
            print_r($response->toArray(false));
            $content = json_decode($response->getContent());
            var_dump($content);
        } catch (ServerException $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
        return 0;
    }
}