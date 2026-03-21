<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Service;

use Elliptic\EC;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\Key;

class FairService
{
    public function resolveDidWeb(string $extensionKey): string
    {
        return sprintf('did:web:extensions.typo3.org:%s', $extensionKey);
    }

    /**
     * Derive the static rotation (recovery) key from TYPO3 API credentials and a per-extension salt.
     *
     * Uses HKDF-SHA256 so the same credentials + salt always produce the same key,
     * while remaining independent of any fresh random material.
     *
     * @param string $username TYPO3 API username
     * @param string $password TYPO3 API password
     * @param string $salt     Per-extension hex salt stored in keys.json
     */
    public function deriveRecoveryKey(string $username, string $password, string $salt): EcKey
    {
        $ikm = serialize(['user' => $username, 'pass' => $password]);
        $derivedBytes = hash_hkdf('sha256', $ikm, 32, 'did-plc-rotation-key', $salt);
        $ec = new EC(Key::CURVE_K256);
        return new EcKey($ec->keyFromPrivate(bin2hex($derivedBytes), 'hex'), Key::CURVE_K256);
    }
}
