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
}
