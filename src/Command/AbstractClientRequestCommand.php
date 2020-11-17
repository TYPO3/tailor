<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch
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
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\HttpClientFactory;
use TYPO3\Tailor\Service\FormatService;
use TYPO3\Tailor\Service\RequestService;

/**
 * Abstract class to be used by commands, requesting an TER API endpoint
 */
abstract class AbstractClientRequestCommand extends Command
{
    /** @var int */
    private $defaultAuthMethod = HttpClientFactory::ALL_AUTH;

    /** @var int */
    private $resultFormat = FormatService::FORMAT_KEY_VALUE;

    /** @var InputInterface */
    protected $input;

    /** @var RequestService */
    protected $requestService;

    protected function configure(): void
    {
        // General option to get a raw result. Can be used for further processing.
        $this->addOption('raw', 'r', InputOption::VALUE_OPTIONAL, 'Return result as raw object (e.g. json)', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $requestConfiguration = $this->generateRequestConfiguration();
        $requestConfiguration
            ->setRaw($input->getOption('raw') !== false)
            ->setDefaultAuthMethod($this->defaultAuthMethod);

        $this->requestService = new RequestService(
            $requestConfiguration,
            new FormatService(new SymfonyStyle($input, $output), $this->getMessages(), $this->resultFormat)
        );
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

    abstract protected function generateRequestConfiguration(): RequestConfiguration;
    abstract protected function getMessages(): Messages;
}
