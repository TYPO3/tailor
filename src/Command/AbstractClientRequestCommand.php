<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Filesystem\ComposerReader;
use TYPO3\Tailor\Formatter\ConsoleFormatter;
use TYPO3\Tailor\HttpClientFactory;
use TYPO3\Tailor\Service\RequestService;
use TYPO3\Tailor\Writer\ConsoleWriter;

/**
 * Abstract class to be used by commands, requesting an TER API endpoint
 */
abstract class AbstractClientRequestCommand extends Command
{
    /** @var int */
    private $defaultAuthMethod = HttpClientFactory::ALL_AUTH;

    /** @var int */
    private $resultFormat = ConsoleFormatter::FORMAT_KEY_VALUE;

    /** @var bool */
    private $confirmationRequired = false;

    /** @var InputInterface */
    protected $input;

    protected function configure(): void
    {
        // General option to get a raw result. Can be used for further processing.
        $this->addOption('raw', 'r', InputOption::VALUE_OPTIONAL, 'Return result as raw object (e.g. json)', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $io = new SymfonyStyle($input, $output);

        if ($this->confirmationRequired
            && !$io->askQuestion(new ConfirmationQuestion($this->getMessages()->getConfirmation()))
        ) {
            $io->writeln('<info>Execution aborted.</info>');
            return 0;
        }

        $requestConfiguration = $this->getRequestConfiguration();
        $requestConfiguration
            ->setRaw($input->getOption('raw') !== false)
            ->setDefaultAuthMethod($this->defaultAuthMethod);

        // RequestService returns a boolean for whether the request was successful or not.
        // Since we have to return an exit code, this must be negated and casted to return
        // 0 on success and 1 on failure.
        return (int)!(new RequestService(
            $requestConfiguration,
            new ConsoleWriter($io, $this->getMessages(), $this->resultFormat)
        ))->run();
    }

    protected function setDefaultAuthMethod(int $defaultAuthMethod): self
    {
        $this->defaultAuthMethod = $defaultAuthMethod;
        return $this;
    }

    protected function setResultFormat(int $resultFormat): self
    {
        $this->resultFormat = $resultFormat;
        return $this;
    }

    protected function setConfirmationRequired(bool $confirmationRequired): self
    {
        $this->confirmationRequired = $confirmationRequired;
        return $this;
    }

    protected function getExtensionKey(InputInterface $input): string
    {
        if ($input->hasArgument('extensionkey')
            && ($key = ($input->getArgument('extensionkey') ?? '')) !== ''
        ) {
            $extensionKey = $key;
        } elseif (Variables::has('TYPO3_EXTENSION_KEY')) {
            $extensionKey = Variables::get('TYPO3_EXTENSION_KEY');
        } elseif (($extensionKeyFromComposer = (new ComposerReader())->getExtensionKey()) !== '') {
            $extensionKey = $extensionKeyFromComposer;
        } else {
            throw new ExtensionKeyMissingException(
                'The extension key must either be set as argument, as environment variable or in the composer.json.',
                1605706548
            );
        }

        return $extensionKey;
    }

    abstract protected function getRequestConfiguration(): RequestConfiguration;
    abstract protected function getMessages(): Messages;
}
