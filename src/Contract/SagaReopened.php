<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Contract;

/**
 * Saga reopened
 *
 * @psalm-readonly
 */
final class SagaReopened
{
    /**
     * Saga identifier.
     *
     * @var string
     */
    public $id;

    /**
     * Operation date.
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * New date of expiration.
     *
     * @var \DateTimeImmutable
     */
    public $expirationDate;

    /**
     * Reason
     *
     * @var string
     */
    public $reason;

    public function __construct(string $id, \DateTimeImmutable $datetime, \DateTimeImmutable $expirationDate, string $reason)
    {
        $this->id             = $id;
        $this->datetime       = $datetime;
        $this->expirationDate = $expirationDate;
        $this->reason         = $reason;
    }
}
