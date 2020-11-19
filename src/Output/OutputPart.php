<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Output;

/**
 * An output part contains the values to be written using the defined output style
 */
class OutputPart implements OutputPartInterface
{
    public const OUTPUT_WRITE_LINE = 'writeln';
    public const OUTPUT_TABLE = 'table';

    /** @var array */
    protected $values;

    /** @var string */
    protected $style;

    public function __construct(array $values, string $style = self::OUTPUT_WRITE_LINE)
    {
        $this->values = $values;
        $this->style = $style;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getOutputStyle(): string
    {
        return $this->style;
    }
}
