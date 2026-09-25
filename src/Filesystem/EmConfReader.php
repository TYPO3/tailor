<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Filesystem;

/**
 * Reading information from ext_emconf.php
 */
class EmConfReader
{
    /** @var array */
    protected $configuration = [];

    public function __construct(string $path = '')
    {
        $filename = rtrim($path ?: (string)(getcwd() ?: '.'), '/') . '/ext_emconf.php';
        if (!file_exists($filename)) {
            return;
        }

        $_EXTKEY = 'dummy';
        @include $filename;

        if (!isset($EM_CONF) || !is_array($EM_CONF)) {
            return;
        }

        $configuration = reset($EM_CONF);
        if (is_array($configuration)) {
            $this->configuration = $configuration;
        }
    }

    public function getVersion(): string
    {
        return trim((string)($this->configuration['version'] ?? ''));
    }
}
