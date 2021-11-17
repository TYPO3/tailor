<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Formatter;

use TYPO3\Tailor\Output\OutputPart;
use TYPO3\Tailor\Output\OutputParts;

/**
 * Format the console output, especially the response content
 */
class ConsoleFormatter
{
    public const FORMAT_NONE = 0;
    public const FORMAT_KEY_VALUE = 1;
    public const FORMAT_DETAIL = 2;
    public const FORMAT_TABLE = 3;

    /** @var int */
    protected $formatType;

    /** @var OutputParts */
    protected $formattedParts;

    public function __construct(int $formatType)
    {
        $this->formatType = $formatType;
        $this->formattedParts = new OutputParts();
    }

    public function format(array $content): OutputParts
    {
        switch ($this->formatType) {
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

        return $this->formattedParts;
    }

    protected function formatKeyValue(array $content): void
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                // Not a key value pair
                continue;
            }
            if (!is_string($key)) {
                // Just output the value for a non-string key
                $this->formattedParts->addPart(new OutputPart([(string)$value]));
                continue;
            }
            $this->formattedParts->addPart(
                new OutputPart([sprintf('%s: %s', '<info>' . $this->normalizeFieldName($key) . '</info>', (string)$value)])
            );
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
                    $this->formattedParts->addPart(new OutputPart([PHP_EOL . $this->normalizeFieldName($key)]));
                }
                $this->formatDetailsResult($value);
            }
            if (is_array($value) || (string)$value === 'Array' || (is_string($value) && $value === '')) {
                continue;
            }
            if (!is_string($key)) {
                $this->formattedParts->addPart(new OutputPart([(string)$value]));
                continue;
            }
            $this->formattedParts->addPart(
                new OutputPart([sprintf('%s: %s', '<info>' . $this->normalizeFieldName($key) . '</info>', (string)$value)])
            );
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
        $this->formattedParts->addPart(
            new OutputPart(
                [
                    ['Extension Key', 'Title', 'Latest Version', 'Last Updated on', 'Composer Name'],
                    $extensions,
                ],
                OutputPart::OUTPUT_TABLE
            )
        );
        $this->formattedParts->addPart(
            new OutputPart([($extensions === [] ? 'No extensions found for options ' : '') . $this->getPaginationOptions($content)])
        );
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
