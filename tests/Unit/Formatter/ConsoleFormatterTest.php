<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Formatter;

use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Formatter\ConsoleFormatter;
use TYPO3\Tailor\Output\OutputPart;

class ConsoleFormatterTest extends TestCase
{
    /**
     * @test
     * @dataProvider formatReturnsFormattedPartsDataProvider
     *
     * @param array $content
     * @param array $expectedValues
     * @param array $expectedOutputStyle
     * @param int $formatType
     */
    public function formatReturnsFormattedParts(
        array $content,
        array $expectedValues,
        array $expectedOutputStyle,
        int $formatType
    ): void {
        $formattedParts = (new ConsoleFormatter($formatType))->format($content);

        self::assertSame(count($expectedValues), $formattedParts->count());

        foreach ($formattedParts->getParts() as $part) {
            self::assertSame($part->getValues(), array_shift($expectedValues));
            self::assertSame($part->getOutputStyle(), array_shift($expectedOutputStyle) ?? OutputPart::OUTPUT_WRITE_LINE);
        }
    }

    /**
     * Data provider for formatReturnsFormattedParts
     *
     * @return \Generator
     */
    public function formatReturnsFormattedPartsDataProvider(): \Generator
    {
        yield 'No output' => [
            [
                'some' => [
                    'dummy' => 'data'
                ]
            ],
            [],
            [],
            ConsoleFormatter::FORMAT_NONE
        ];
        yield 'Simple key/values array' => [
            [
                'dummy' => 'data',
                'noKey',
                'notKeyValue' => [
                    'foo' => 'bar'
                ],
                'some_Key' => 'otherData',
            ],
            [
                ['<info>Dummy</info>: data'],
                ['noKey'],
                ['<info>Some Key</info>: otherData'],
            ],
            [],
            ConsoleFormatter::FORMAT_KEY_VALUE
        ];
        yield 'Extension details list' => [
            [
                'key' => 'some_ext',
                'downloads' => 60,
                'version_count' => 2,
                'meta' => [
                    'composer_name' => 'vendor/some_ext',
                    'tags' => [
                        [
                            'title' => 'sometag'
                        ],
                        [
                            'title' => 'anothertag'
                        ]
                    ]
                ],
                'current_version' => [
                    'title' => 'foobar',
                    'description' => 'barbaz',
                    'number' => '1.0.0',
                    'typo3_versions' => [
                        9, 10
                    ],
                    'download' => [
                        'composer' => 'composer req vendor/some_ext'
                    ]
                ]
            ],
            [
                ['<info>Key</info>: some_ext'],
                ['<info>Downloads</info>: 60'],
                ['<info>Version count</info>: 2'],
                ['
Meta'],
                ['<info>Composer name</info>: vendor/some_ext'],
                ['
Tags'],
                ['<info>Title</info>: sometag'],
                ['<info>Title</info>: anothertag'],
                ['
Current version'],
                ['<info>Title</info>: foobar'],
                ['<info>Description</info>: barbaz'],
                ['<info>Number</info>: 1.0.0'],
                ['
Typo3 versions'],
                ['9'],
                ['10'],
                ['
Download'],
                ['<info>Composer</info>: composer req vendor/some_ext'],
            ],
            [],
            ConsoleFormatter::FORMAT_DETAIL
        ];
        yield 'Find extensions result' => [
            [
                'results' => 2,
                'page' => 1,
                'per_page' => 2,
                'filter' => [
                    'username' => 'some_user'
                ],
                'extensions' => [
                    [
                        'key' => 'some_ext',
                        'meta' => [
                            'composer_name' => 'vendor/some_ext'
                        ],
                        'current_version' => [
                            'title' => 'foobar',
                            'number' => '1.0.0',
                            'upload_date' => 1605785659
                        ]
                    ],
                    [
                        'key' => 'another_ext'
                    ]
                ]
            ],
            [
                [
                    ['Extension Key', 'Title', 'Latest Version', 'Last Updated on', 'Composer Name'],
                    [
                        'another_ext' => ['another_ext', '-', '-', '-', '-'],
                        'some_ext' => ['some_ext', 'foobar', '1.0.0', '19.11.2020', 'vendor/some_ext']
                    ]
                ],
                ['Page: 1, Per page: 2, Filter: some_user (Author)']
            ],
            [
                OutputPart::OUTPUT_TABLE,
                OutputPart::OUTPUT_WRITE_LINE
            ],
            ConsoleFormatter::FORMAT_TABLE
        ];
    }
}
