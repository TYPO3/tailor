<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Validation;

/**
 * Check that a composer.json describes a TYPO3 extension TER accepts:
 * the version it declares, if any, matches the given version, and the
 * TYPO3 core is required, unless an ext_emconf.php declares that constraint.
 */
class ComposerJsonValidator
{
    private const EXTENSION_TYPE = 'typo3-cms-extension';
    private const CORE_PACKAGES = ['typo3/cms-core', 'typo3/cms'];

    /** @var array|null Decoded manifest, NULL when the file is missing or unreadable */
    protected ?array $manifest = null;

    /**
     * @param string $composerJsonPath Full path to the composer.json file
     */
    public function __construct(protected string $composerJsonPath)
    {
        $this->manifest = $this->readManifest();
    }

    /**
     * @param string $givenVersion The version to release
     * @param bool $typo3ConstraintDeclaredElsewhere TRUE when an ext_emconf.php declares the TYPO3 constraint
     * @return list<ComposerJsonValidationError> List of validation errors. If empty, the composer.json is valid.
     */
    public function collectErrors(string $givenVersion, bool $typo3ConstraintDeclaredElsewhere = false): array
    {
        if (!file_exists($this->composerJsonPath)) {
            return [ComposerJsonValidationError::NOT_FOUND];
        }
        if ($this->manifest === null) {
            return [ComposerJsonValidationError::UNREADABLE];
        }
        if (($this->manifest['type'] ?? null) !== self::EXTENSION_TYPE) {
            return [ComposerJsonValidationError::NOT_AN_EXTENSION];
        }

        $errors = [];
        $declaredVersion = $this->getDeclaredVersion();
        if ($declaredVersion !== null && $declaredVersion !== $givenVersion) {
            $errors[] = ComposerJsonValidationError::EXTENSION_VERSION_MISMATCH;
        }
        if ($this->declaresRequire()) {
            if (array_intersect(self::CORE_PACKAGES, array_keys($this->manifest['require'])) === []) {
                $errors[] = ComposerJsonValidationError::MISSING_TYPO3_VERSION_CONSTRAINT;
            }
        } elseif (!$typo3ConstraintDeclaredElsewhere) {
            $errors[] = ComposerJsonValidationError::MISSING_TYPO3_VERSION_CONSTRAINT;
        }

        return $errors;
    }

    public function isValid(string $givenVersion, bool $typo3ConstraintDeclaredElsewhere = false): bool
    {
        return $this->collectErrors($givenVersion, $typo3ConstraintDeclaredElsewhere) === [];
    }

    /**
     * Whether the manifest carries a version TER would take over the ext_emconf.php one
     */
    public function declaresVersion(): bool
    {
        return $this->getDeclaredVersion() !== null;
    }

    /**
     * Whether the manifest carries constraints TER would take over the ext_emconf.php ones
     */
    public function declaresRequire(): bool
    {
        return is_array($this->manifest['require'] ?? null) && $this->manifest['require'] !== [];
    }

    protected function getDeclaredVersion(): ?string
    {
        $version = $this->manifest['extra']['typo3/cms']['version'] ?? $this->manifest['version'] ?? null;

        return is_scalar($version) && (string)$version !== '' ? (string)$version : null;
    }

    protected function readManifest(): ?array
    {
        if (!is_file($this->composerJsonPath)) {
            return null;
        }
        $content = @file_get_contents($this->composerJsonPath);
        if ($content === false) {
            return null;
        }
        $manifest = json_decode($content, true);

        return is_array($manifest) ? $manifest : null;
    }
}
