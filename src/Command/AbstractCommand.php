<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project - inspiring people to share!
 * (c) 2020-2023 Oliver Bartsch, Benni Mack & Elias Häußler
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Filesystem\ComposerReader;

/**
 * Abstract class for tailor console commands.
 */
abstract class AbstractCommand extends Command
{
    protected function getExtensionKey(InputInterface $input): string
    {
        if ($input->hasArgument('extensionkey')
            && ($key = ($input->getArgument('extensionkey') ?? '')) !== ''
        ) {
            $extensionKey = $key;
        } elseif (Variables::has('TYPO3_EXTENSION_KEY')) {
            $extensionKey = Variables::get('TYPO3_EXTENSION_KEY');
        } elseif (($extensionKeyFromComposer = (new ComposerReader())->getExtensionKey()) !== '') {
            $extensionKey = $extensionKeyFromComposer;
        } else {
            throw new ExtensionKeyMissingException(
                'The extension key must either be set as argument, as environment variable or in the composer.json.',
                1605706548
            );
        }

        return $extensionKey;
    }
}
