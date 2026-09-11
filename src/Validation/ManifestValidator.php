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
 * Validate the manifests at the root of an extension the way TER reads them.
 *
 * A composer.json is the manifest. When it is present it has to be usable,
 * and what it declares wins: its version over the ext_emconf.php version,
 * its "require" over the ext_emconf.php constraints. An ext_emconf.php
 * beside it is only checked for what composer.json does not declare.
 * Without a composer.json the ext_emconf.php is validated as before.
 */
class ManifestValidator
{
    private const EMCONF_STRUCTURAL_ERRORS = [
        EmConfValidationError::NOT_FOUND,
        EmConfValidationError::MISSING_CONFIGURATION,
        EmConfValidationError::UNSUPPORTED_TYPE,
    ];

    /**
     * @param string|null $composerJsonPath Full path to the root-level composer.json, NULL when there is none
     * @param string|null $emConfPath Full path to the root-level ext_emconf.php, NULL when there is none
     */
    public function __construct(
        protected ?string $composerJsonPath,
        protected ?string $emConfPath,
    ) {}

    /**
     * @return list<string> Human readable errors. If empty, the manifests are valid.
     */
    public function collectErrors(string $givenVersion): array
    {
        if ($this->composerJsonPath === null && $this->emConfPath === null) {
            return ['Neither a `composer.json` nor an `ext_emconf.php` file was found at the root of the extension.'];
        }

        $emConfErrors = $this->emConfPath !== null
            ? (new EmConfVersionValidator($this->emConfPath))->collectErrors($givenVersion)
            : [];

        if ($this->composerJsonPath === null) {
            return array_map(static fn(EmConfValidationError $error): string => $error->getErrorMessage(), $emConfErrors);
        }

        $composerJson = new ComposerJsonValidator($this->composerJsonPath);
        $emConfDeclaresTypo3Constraint = $this->emConfPath !== null
            && !$this->containsAny($emConfErrors, self::EMCONF_STRUCTURAL_ERRORS)
            && !in_array(EmConfValidationError::MISSING_TYPO3_VERSION_CONSTRAINT, $emConfErrors, true);

        $errors = array_map(
            static fn(ComposerJsonValidationError $error): string => $error->getErrorMessage(),
            $composerJson->collectErrors($givenVersion, $emConfDeclaresTypo3Constraint)
        );

        // Next to a composer.json the ext_emconf.php may omit what composer.json
        // declares - and a version at all, because TER takes it from the request.
        $ignored = [EmConfValidationError::MISSING_EXTENSION_VERSION];
        if ($composerJson->declaresVersion()) {
            $ignored[] = EmConfValidationError::EXTENSION_VERSION_MISMATCH;
        }
        if ($composerJson->declaresRequire() || !$emConfDeclaresTypo3Constraint) {
            $ignored[] = EmConfValidationError::MISSING_TYPO3_VERSION_CONSTRAINT;
        }
        foreach ($emConfErrors as $error) {
            if (!in_array($error, $ignored, true)) {
                $errors[] = $error->getErrorMessage();
            }
        }

        return array_values($errors);
    }

    /**
     * @param list<EmConfValidationError> $errors
     * @param list<EmConfValidationError> $candidates
     */
    private function containsAny(array $errors, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $errors, true)) {
                return true;
            }
        }

        return false;
    }
}
