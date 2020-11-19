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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Formatter\ConsoleFormatter;

/**
 * Command for TER REST endpoint `PUT /extension/{key}`
 */
class UpdateExtensionCommand extends AbstractClientRequestCommand
{
    private const OPTION_TO_FORM_DATA_MAPPING = [
        'composer' => 'composer_name',
        'issues' => 'forge_link',
        'repository' => 'repository_url',
        'manual' => 'external_manual',
        'paypal' => 'paypal_url',
        'tags' => 'tags',
    ];

    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Update extension meta information')
            ->setResultFormat(ConsoleFormatter::FORMAT_DETAIL)
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key')
            ->addOption('composer', '', InputOption::VALUE_OPTIONAL, 'The extensions composer name')
            ->addOption('issues', '', InputOption::VALUE_OPTIONAL, 'Link to the issue tracker')
            ->addOption('repository', '', InputOption::VALUE_OPTIONAL, 'Link to the repository')
            ->addOption('manual', '', InputOption::VALUE_OPTIONAL, 'Link to the external manual')
            ->addOption('paypal', '', InputOption::VALUE_OPTIONAL, 'Link to sponsoring page (paypal)')
            ->addOption('tags', '', InputOption::VALUE_OPTIONAL, 'Comma-separated list of tags');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $this->getExtensionKey($input);
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration(
            'PUT',
            'extension/' . $this->extensionKey,
            [],
            $this->getFormData(),
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Updating meta information of extension %s', $this->extensionKey),
            sprintf('Meta information of extension %s successfully updated.', $this->extensionKey),
            sprintf('Could not update meta information of extension %s.', $this->extensionKey)
        );
    }

    private function getFormData(): array
    {
        $options = $this->input->getOptions();
        $formData = [];

        foreach (self::OPTION_TO_FORM_DATA_MAPPING as $optionName => $formName) {
            if ($options[$optionName] !== null) {
                $formData[$formName] = $options[$optionName];
            }
        }

        return $formData;
    }
}
