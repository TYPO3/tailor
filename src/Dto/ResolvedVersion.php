<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Dto;

/**
 * A version together with the source it was taken from.
 *
 * Since the version does not have to be stated on the command line,
 * commands can tell the user where the version they work with comes from.
 */
class ResolvedVersion
{
    public const SOURCE_ARGUMENT = 'argument';
    public const SOURCE_GIT_TAG = 'the tag of the checked out commit';
    public const SOURCE_EMCONF = 'ext_emconf.php';
    public const SOURCE_COMPOSER = 'composer.json';

    /** @var string */
    protected $version;

    /** @var string */
    protected $source;

    public function __construct(string $version, string $source)
    {
        $this->version = $version;
        $this->source = $source;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function isFromArgument(): bool
    {
        return $this->source === self::SOURCE_ARGUMENT;
    }
}
