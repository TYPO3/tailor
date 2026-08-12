<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Service\GitService;
use TYPO3\Tailor\Tests\Unit\GitRepositoryTrait;

class GitServiceTest extends TestCase
{
    use GitRepositoryTrait;

    /** @var string */
    private $repositoryPath = '';

    protected function setUp(): void
    {
        $this->repositoryPath = sys_get_temp_dir() . '/tailor-git-' . bin2hex(random_bytes(6));
        mkdir($this->repositoryPath, 0777, true);
        file_put_contents($this->repositoryPath . '/ext_emconf.php', '<?php');
    }

    protected function tearDown(): void
    {
        if ($this->repositoryPath === '' || !is_dir($this->repositoryPath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->repositoryPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->repositoryPath);
        $this->repositoryPath = '';
    }

    #[Test]
    public function tagOfTheCheckedOutCommitIsReturned(): void
    {
        $this->createGitRepository($this->repositoryPath, '1.2.3');

        self::assertSame(['1.2.3'], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function versionPrefixIsStripped(): void
    {
        $this->createGitRepository($this->repositoryPath, 'v1.2.3');

        self::assertSame(['1.2.3'], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function tagsWhichAreNoVersionAreIgnored(): void
    {
        $this->createGitRepository($this->repositoryPath, 'latest', '1.2.3', 'release-1.2.3');

        self::assertSame(['1.2.3'], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function prefixedAndUnprefixedTagOfTheSameVersionAreReturnedOnce(): void
    {
        $this->createGitRepository($this->repositoryPath, '1.2.3', 'v1.2.3');

        self::assertSame(['1.2.3'], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function allVersionsOfTheCheckedOutCommitAreReturned(): void
    {
        $this->createGitRepository($this->repositoryPath, '1.2.3', '1.2.4');

        self::assertSame(['1.2.3', '1.2.4'], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function untaggedRepositoryReturnsNoVersion(): void
    {
        $this->createGitRepository($this->repositoryPath);

        self::assertSame([], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function pathWithoutRepositoryReturnsNoVersion(): void
    {
        self::assertSame([], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath));
    }

    #[Test]
    public function nonExistingPathReturnsNoVersion(): void
    {
        self::assertSame([], (new GitService())->getVersionsFromTagsOfHead($this->repositoryPath . '/does-not-exist'));
    }
}
