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
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Filesystem\ComposerReader;

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

    public static function getExtensionKeyFromInput(InputInterface $input): string
    {
        // 1. CLI argument has highest priority
        if ($input->hasArgument('extensionkey')
            && ($key = ($input->getArgument('extensionkey') ?? '')) !== ''
        ) {
            return $key;
        }

        // 2. composer.json is the recommended source
        $extensionKeyFromComposer = (new ComposerReader())->getExtensionKey();
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
