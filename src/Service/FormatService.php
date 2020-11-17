<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Dto\Messages;

/**
 * Service for formatting the console output, especially the response content
 */
class FormatService
{
    public const FORMAT_NONE = 0;
    public const FORMAT_KEY_VALUE = 1;
    public const FORMAT_DETAIL = 2;
    public const FORMAT_TABLE = 3;

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
        if (is_callable([$this->messages, $name])) {
            $this->io->writeln($this->messages->{$name}());
        }
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
        $this->io->warning($this->messages->getFailure() . PHP_EOL . 'Reason: ' .  $reason);
    }

    public function formatResult(array $content): void
    {
        switch ($this->resultFormat) {
            case self::FORMAT_NONE:
                break;
            case self::FORMAT_DETAIL:
                $this->formatDetailsResult($content);
                break;
            case self::FORMAT_TABLE:
                $this->formatTable($content);
                break;
            case self::FORMAT_KEY_VALUE:
            default:
                $this->formatKeyValue($content);
                break;
        }
    }

    protected function formatKeyValue(array $content): void
    {
        foreach ($content as $key => $value) {
            if (!is_string($key)) {
                $this->io->writeln((string)$value);
                continue;
            }
            $this->io->writeln(sprintf('%s: %s', '<info>' . $this->normalizeFieldName($key) . '</info>', (string)$value));
        }
    }

    protected function formatDetailsResult(array $content): void
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }
                if (is_string($key)) {
                    $this->io->writeln(PHP_EOL . $this->normalizeFieldName($key));
                }
                $this->formatDetailsResult($value);
            }
            if ((string)$value === 'Array') {
                continue;
            }
            if (!is_string($key)) {
                $this->io->writeln((string)$value);
                continue;
            }
            $this->io->writeln(sprintf('%s: %s', '<info>' . $this->normalizeFieldName($key) . '</info>', (string)$value));
        }
    }

    protected function formatTable(array $content): void
    {
        $extensions = [];
        foreach ($content['extensions'] as $extensionData) {
            $extensions[$extensionData['key']] = [
                $extensionData['key'],
                $extensionData['current_version']['title'] ?? '-',
                $extensionData['current_version']['number'] ?? '-',
                isset($extensionData['current_version']['upload_date']) ? date('d.m.Y', $extensionData['current_version']['upload_date']) : '-',
                $extensionData['meta']['composer_name'] ?? '-',
            ];
        }
        ksort($extensions);
        $this->io->table(['Extension Key', 'Title', 'Latest Version', 'Last Updated on', 'Composer Name'], $extensions);
        $this->io->writeln(($extensions === [] ? 'No extensions found for options ' : '') . $this->getPaginationOptions($content));
    }

    protected function normalizeFieldName(string $fieldName): string
    {
        return ucfirst(implode(' ', explode('_', $fieldName)));
    }

    protected function getPaginationOptions(array $content): string
    {
        return sprintf(
            'Page: %d, Per page: %d, Filter: %s',
            $content['page'],
            $content['per_page'],
            $this->getFilterString($content['filter'])
        );
    }

    protected function getFilterString(array $filter): string
    {
        if ($filter === []) {
            return '-';
        }

        $content = '';

        if (!empty($filter['username'])) {
            $content .= $filter['username'] . ' (Author)';
        }

        if (!empty($filter['typo3_version'])) {
            $content .= ', ' . $filter['typo3_version'] . ' (TYPO3 version)';
        }

        return trim($content, ', ');
    }
}
