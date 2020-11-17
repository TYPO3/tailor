<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Dto;

/**
 * DTO of console messages for an individual request
 */
class Messages
{
    /** @var string */
    protected $title;

    /** @var string */
    protected $success;

    /** @var string */
    protected $failure;

    public function __construct(string $title = '', string $success = '', string $failure = '')
    {
        $this->title = $title ?: 'Starting the command.';
        $this->success = $success ?: 'Request was successful.';
        $this->failure = $failure ?: 'Request has failed.';
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
}
