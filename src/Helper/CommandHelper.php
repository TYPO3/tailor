<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project - inspiring people to share!
 * (c) 2020-2024 Oliver Bartsch, Benni Mack & Elias Häußler
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Helper;

use Symfony\Component\Console\Input\InputInterface;
use TYPO3\Tailor\Dto\ResolvedVersion;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Exception\VersionMissingException;
use TYPO3\Tailor\Filesystem\ComposerReader;
use TYPO3\Tailor\Filesystem\EmConfReader;
use TYPO3\Tailor\Service\GitService;
use TYPO3\Tailor\Validation\VersionValidator;

/**
 * Helper class for console commands.
 */
final class CommandHelper
{
    /**
     * Terms every TER listing already implies. TER only lists TYPO3 extensions,
     * so these narrow nothing down there — unlike on GitHub or Packagist, where
     * the same terms are what make a package findable at all.
     *
     * Compared after stripping separators, so the spelling variants that occur
     * in practice — "typo3 cms" in composer.json keywords, "typo3-cms-extension"
     * in GitHub topics — are all recognised.
     *
     * @var string[]
     */
    private const TAGS_IMPLIED_BY_TER = [
        'cms',
        'cmsextension',
        'extension',
        'extensions',
        'php',
        'ter',
        'typo3',
        'typo3cms',
        'typo3cmsextension',
        'typo3ext',
        'typo3extension',
    ];

    /**
     * The version is looked up in the extension itself if not given as argument,
     * so a release does not have to repeat its version on the command line.
     */
    public static function getVersionFromInput(InputInterface $input): ResolvedVersion
    {
        // 1. CLI argument has highest priority
        $version = (string)($input->getArgument('version') ?? '');
        if ($version !== '') {
            return new ResolvedVersion($version, ResolvedVersion::SOURCE_ARGUMENT);
        }

        $path = self::getPathFromInput($input);

        // 2. The tag of the checked out commit marks the released version
        $versions = (new GitService())->getVersionsFromTagsOfHead($path);

        if (count($versions) > 1) {
            throw new VersionMissingException(
                sprintf(
                    'The checked out commit is tagged with more than one version (%s). Please state the version to use as argument.',
                    implode(', ', $versions)
                ),
                1786492801
            );
        }

        if ($versions !== []) {
            return new ResolvedVersion($versions[0], ResolvedVersion::SOURCE_GIT_TAG);
        }

        // 3. The version files, as written by the `set-version` command
        $versionValidator = new VersionValidator();

        $version = (new EmConfReader($path))->getVersion();
        if ($versionValidator->isValid($version)) {
            return new ResolvedVersion($version, ResolvedVersion::SOURCE_EMCONF);
        }

        $version = (new ComposerReader($path))->getVersion();
        if ($versionValidator->isValid($version)) {
            return new ResolvedVersion($version, ResolvedVersion::SOURCE_COMPOSER);
        }

        throw new VersionMissingException(
            'The version must either be set as argument, or be available in the tag of the checked out commit, '
            . 'in `ext_emconf.php` or in `composer.json`.',
            1786492802
        );
    }

    /**
     * Move the extension key to its argument if it was passed as only argument.
     *
     * Since the version argument comes first and is optional, a single argument
     * is ambiguous. Extension keys never look like a version though, so
     * `ter:publish my_extension` can safely be told apart from `ter:publish 1.2.3`.
     */
    public static function normalizeVersionAndExtensionKeyArguments(InputInterface $input): void
    {
        if (!$input->hasArgument('version') || !$input->hasArgument('extensionkey')) {
            return;
        }

        $version = (string)($input->getArgument('version') ?? '');
        $extensionKey = (string)($input->getArgument('extensionkey') ?? '');

        if ($extensionKey !== '' || !preg_match('/^[a-z][a-z0-9_]+$/', $version)) {
            return;
        }

        $input->setArgument('extensionkey', $version);
        $input->setArgument('version', null);
    }

    /**
     * The path of the extension to work with. Defaults to the current working directory.
     */
    public static function getPathFromInput(InputInterface $input): string
    {
        $path = $input->hasOption('path') ? (string)($input->getOption('path') ?? '') : '';

        return $path !== '' ? $path : (string)(getcwd() ?: '.');
    }

    public static function getExtensionKeyFromInput(InputInterface $input): string
    {
        // 1. CLI argument has highest priority
        if ($input->hasArgument('extensionkey')
            && ($key = ($input->getArgument('extensionkey') ?? '')) !== ''
        ) {
            return $key;
        }

        // 2. composer.json of the extension is the recommended source
        $extensionKeyFromComposer = (new ComposerReader(self::getPathFromInput($input)))->getExtensionKey();
        if ($extensionKeyFromComposer !== '') {
            return $extensionKeyFromComposer;
        }

        // 3. Environment variable only for backwards compatibility
        if (Variables::has('TYPO3_EXTENSION_KEY')) {
            trigger_error(
                'Using TYPO3_EXTENSION_KEY environment variable is deprecated. '
                . 'Please set the extension key in composer.json at [extra][typo3/cms][extension-key] instead.',
                E_USER_DEPRECATED
            );
            return Variables::get('TYPO3_EXTENSION_KEY');
        }

        throw new ExtensionKeyMissingException(
            'The extension key must either be set as argument or in composer.json at [extra][typo3/cms][extension-key].',
            1605706548
        );
    }

    /**
     * Returns those of the given tags that TER already implies, so the caller
     * can point them out. Case and separators are ignored.
     *
     * @return string[]
     */
    public static function getTagsImpliedByTer(string $tags): array
    {
        $implied = [];

        foreach (explode(',', $tags) as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }

            $normalized = strtolower((string)preg_replace('/[^a-z0-9]/i', '', $tag));
            if (in_array($normalized, self::TAGS_IMPLIED_BY_TER, true)) {
                $implied[] = $tag;
            }
        }

        return $implied;
    }
}
