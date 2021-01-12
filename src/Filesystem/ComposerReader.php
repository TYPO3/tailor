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

use TYPO3\Tailor\Exception\InvalidComposerJsonException;

/**
 * Reading information from composer.json
 */
class ComposerReader
{
    /** @var array */
    protected $composerSchema = [];

    public function __construct(string $path = '')
    {
        $filename = rtrim($path ?: (string)(getcwd() ?: '.'), '/') . '/composer.json';
        if (!file_exists($filename)) {
            return;
        }
        $content = @file_get_contents($filename);
        if ($content === false) {
            return;
        }
        $this->composerSchema = json_decode($content, true);
        if (!$this->composerSchema || $this->composerSchema === []) {
            throw new InvalidComposerJsonException('The composer.json found is invalid!', 1610442954);
        }
    }

    public function getExtensionKey(): string
    {
        return $this->composerSchema['extra']['typo3/cms']['extension-key'] ?? '';
    }
}
