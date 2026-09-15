<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit;

/**
 * Creates real git repositories for tests, since the commands ask
 * the git binary itself instead of parsing the repository files.
 */
trait GitRepositoryTrait
{
    /**
     * Initialize a repository in the given directory, commit its current
     * content and tag that commit with all given tags.
     */
    private function createGitRepository(string $path, string ...$tags): void
    {
        if (!self::isGitAvailable()) {
            self::markTestSkipped('The git binary is not available.');
        }

        $this->git($path, 'init -q');
        $this->git($path, 'add -A');
        $this->git($path, 'commit -q -m "Add extension"');

        foreach ($tags as $tag) {
            $this->git($path, 'tag ' . escapeshellarg($tag));
        }
    }

    private function git(string $path, string $arguments): void
    {
        $command = sprintf(
            'git -C %s -c user.name=Tailor -c user.email=tailor@example.org -c commit.gpgsign=false %s 2>&1',
            escapeshellarg($path),
            $arguments
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail(sprintf('Command "%s" failed: %s', $command, implode(PHP_EOL, $output)));
        }
    }

    private static function isGitAvailable(): bool
    {
        $output = [];
        $exitCode = 0;
        exec('git --version 2>&1', $output, $exitCode);

        return $exitCode === 0;
    }
}
