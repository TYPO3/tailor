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

class RegisterExtensionCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Register a new extension key in TER')
            ->addArgument(
                'extensionkey',
                InputArgument::REQUIRED,
                'Define an extension key'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $extensionKey = $input->getArgument('extensionkey');
        $io->title('Register the extension key ' . $extensionKey);
        try {
            $client = HttpClientFactory::create('v1');
            $response = $client->request('POST', 'extension/' . $extensionKey);
            if ($response->getStatusCode() === 409) {
                $response = $client->request('GET', 'extension/' . $extensionKey);
                $content = json_decode($response->getContent());
                $io->error('Sorry, but the extension key "' . $content[0]->key . '" is already in use');
            } else {
                $content = json_decode($response->getContent());
                $io->success('Extension Key "' . $content->key . '" was successfully registered to ' . $content->owner);
            }
        } catch (ServerException $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
        return 0;
    }
}