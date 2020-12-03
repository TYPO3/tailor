<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Dto;

/**
 * DTO for console messages of an individual request
 */
class Messages
{
    /** @var string */
    protected $title;

    /** @var string */
    protected $success;

    /** @var string */
    protected $failure;

    /** @var string */
    protected $confirmation;

    public function __construct(
        string $title = '',
        string $success = '',
        string $failure = '',
        string $confirmation = ''
    ) {
        $this->title = $title ?: 'Starting the command';
        $this->success = $success ?: 'Command execution was successful.';
        $this->failure = $failure ?: 'Command execution has failed.';
        $this->confirmation = $confirmation ?: 'Are you sure you want to continue?';
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSuccess(): string
    {
        return $this->success;
    }

    public function getFailure(): string
    {
        return $this->failure;
    }

    public function getConfirmation(): string
    {
        return $this->confirmation;
    }
}
