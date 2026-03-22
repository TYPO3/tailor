<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Service;

/**
 * Manages the local FAIR configuration directory (~/.config/fairpm/<extension>/).
 *
 * Responsible for path resolution, reading and writing keys.json / did.json,
 * and ensuring the directory is created with the correct permissions.
 */
class FairConfigurationService
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? (($_SERVER['HOME'] ?? '') . '/.config/fairpm');
    }

    public function getConfigDir(string $extensionKey): string
    {
        return $this->baseDir . '/' . $extensionKey;
    }

    public function getKeysFile(string $extensionKey): string
    {
        return $this->getConfigDir($extensionKey) . '/keys.json';
    }

    public function getDidFile(string $extensionKey): string
    {
        return $this->getConfigDir($extensionKey) . '/did.json';
    }

    /**
     * @return string|null `did:plc:...` value (if available)
     */
    public function getDid(string $extensionKey): ?string
    {
        if (!$this->didExists($extensionKey)) {
            return null;
        }
        $didFile = $this->getDidFile($extensionKey);
        $didData = json_decode((string)file_get_contents($didFile), true) ?? [];
        return $didData['did'] ?? null;
    }

    public function didExists(string $extensionKey): bool
    {
        return file_exists($this->getDidFile($extensionKey));
    }

    /**
     * Load keys.json for the given extension key.
     * Returns an empty array if the file does not yet exist.
     *
     * @return array<string, mixed>
     */
    public function loadKeysData(string $extensionKey): array
    {
        $file = $this->getKeysFile($extensionKey);
        if (!file_exists($file)) {
            return [];
        }
        return json_decode((string)file_get_contents($file), true) ?? [];
    }

    /**
     * Load did.json for the given extension key.
     * Returns an empty array if the file does not yet exist.
     *
     * @return array<string, mixed>
     */
    public function loadDidData(string $extensionKey): array
    {
        $file = $this->getDidFile($extensionKey);
        if (!file_exists($file)) {
            return [];
        }
        return json_decode((string)file_get_contents($file), true) ?? [];
    }

    /**
     * Ensure recovery.salt is present in $keysData, generating one if absent.
     *
     * @param array<string, mixed> $keysData
     */
    public function ensureSalt(array &$keysData): void
    {
        if (empty($keysData['recovery']['salt'])) {
            $keysData['recovery']['salt'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Create the config directory for the extension (mode 0700) if it does not exist.
     */
    public function ensureConfigDir(string $extensionKey): void
    {
        $dir = $this->getConfigDir($extensionKey);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    /**
     * Write keys.json, then restrict permissions to 0600.
     *
     * @param array<string, mixed> $keysData
     */
    public function writeKeysData(string $extensionKey, array $keysData): void
    {
        $file = $this->getKeysFile($extensionKey);
        file_put_contents($file, json_encode($keysData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($file, 0600);
    }

    /**
     * Write did.json for the given extension.
     *
     * @param array<string, mixed> $didData
     */
    public function writeDidData(string $extensionKey, array $didData): void
    {
        $file = $this->getDidFile($extensionKey);
        file_put_contents($file, json_encode($didData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
