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
 * Replacing a version in a given file using the given pattern.
 *
 * The pattern must contain one capturing group which is the version
 * string which should be replaced.
 *
 * Note: The provided new version number is not validated. This must
 * be done by the calling code.
 */
class VersionReplacer
{
    /** @var string[] */
    protected $versionParts;

    public function __construct(string $newVersion)
    {
        $this->versionParts = explode('.', $newVersion);
    }

    public function setVersion(string $filePath, string $pattern, int $versionPartsToUse = 3): void
    {
        $newVersion = '';
        for ($i = 0; $i < $versionPartsToUse; $i++) {
            $newVersion .= $this->versionParts[$i] . '.';
        }
        $newVersion = rtrim($newVersion, '.');

        $fileContents = @file_get_contents($filePath);
        if ($fileContents === false) {
            throw new \InvalidArgumentException('The file ' . $filePath . ' could not be opened', 1605741968);
        }
        $updatedFileContents = preg_replace_callback('/' . $pattern . '/u', static function ($matches) use ($newVersion) {
            return str_replace($matches[1], $newVersion, $matches[0]);
        }, $fileContents);
        file_put_contents($filePath, $updatedFileContents);
    }
}
