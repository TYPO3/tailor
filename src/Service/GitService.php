<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Service;

use TYPO3\Tailor\Validation\VersionValidator;

/**
 * Service for reading information from the git repository of an extension
 */
class GitService
{
    /**
     * Return the versions the currently checked out commit is tagged with.
     *
     * Tags may be prefixed with `v`, which is stripped from the returned
     * versions. Tags which are no version at all, e.g. `latest`, are ignored.
     *
     * @param string $path A path within the git repository
     * @return array<int, string> The versions, without duplicates
     */
    public function getVersionsFromTagsOfHead(string $path): array
    {
        $versionValidator = new VersionValidator();
        $versions = [];

        foreach ($this->getTagsOfHead($path) as $tag) {
            $version = (string)preg_replace('/^v/i', '', $tag);

            if ($versionValidator->isValid($version) && !in_array($version, $versions, true)) {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * Return all tags of the currently checked out commit.
     *
     * Anything preventing us from asking git - a missing binary, a disabled
     * exec() or a path which is no repository at all - just means there is
     * no tag to work with.
     *
     * @param string $path A path within the git repository
     * @return array<int, string>
     */
    protected function getTagsOfHead(string $path): array
    {
        if (!function_exists('exec') || !is_dir($path)) {
            return [];
        }

        $output = [];
        $exitCode = 0;

        // stderr is redirected into the captured output, so failures of the
        // command (e.g. no repository) do not show up on the console.
        @exec(sprintf('git -C %s tag --points-at HEAD 2>&1', escapeshellarg($path)), $output, $exitCode);

        if ($exitCode !== 0) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $output)));
    }
}
