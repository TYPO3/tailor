<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Writer;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Formatter\ConsoleFormatter;

/**
 * Write the console output, using the ConsoleFormatter if requested
 */
class ConsoleWriter
{
    /** @var SymfonyStyle */
    protected $io;

    /** @var Messages */
    protected $messages;

    /** @var int */
    protected $resultFormat;

    public function __construct(SymfonyStyle $io, Messages $messages, int $resultFormat)
    {
        $this->io = $io;
        $this->messages = $messages;
        $this->resultFormat = $resultFormat;
    }

    public function __call(string $name, array $arguments)
    {
        if (is_callable([$this->io, $name])) {
            $this->io->{$name}(...$arguments);
        }
    }

    public function writeTitle(): void
    {
        $this->io->title($this->messages->getTitle());
    }

    public function writeSuccess(): void
    {
        $this->io->success($this->messages->getSuccess());
    }

    public function writeFailure(string $reason): void
    {
        $this->io->warning($this->messages->getFailure() . PHP_EOL . 'Reason: ' . $reason);
    }

    public function writeFormattedResult(array $content): void
    {
        foreach ((new ConsoleFormatter($this->resultFormat))->format($content)->getParts() as $part) {
            $this->io->{$part->getOutputStyle()}(...$part->getValues());
        }
    }
}
