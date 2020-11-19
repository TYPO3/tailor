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
 * Replaces a given ext_emconf.php contents with a new version.
 */
class EmConfVersionReplacer
{
    /** @var string */
    protected $filePath;

    /** @var string */
    protected $pattern = '["\']version["\']\s=>\s["\']((?:[0-9]+)\.[0-9]+\.[0-9]+\s*)["\']';

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function setVersion(string $newVersion): void
    {
        $fileContents = file_get_contents($this->filePath);
        if ($fileContents === false) {
            throw new \InvalidArgumentException('The file ' . $this->filePath . ' could not be opened', 1605741968);
        }
        $updatedFileContents = preg_replace_callback('/' . $this->pattern . '/u', static function ($matches) use ($newVersion) {
            return str_replace($matches[1], $newVersion, $matches[0]);
        }, $fileContents);
        file_put_contents($this->filePath, $updatedFileContents);
    }
}
