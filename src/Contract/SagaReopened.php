<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Contract;

/**
 * Saga reopened
 *
 * @psalm-immutable
 */
final class SagaReopened
{
    /**
     * Saga identifier.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $id;

    /**
     * Operation date.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * New date of expiration.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $expirationDate;

    /**
     * Reason
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $reason;

    public function __construct(
        string $id,
        \DateTimeImmutable $datetime,
        \DateTimeImmutable $expirationDate,
        string $reason
    ) {
        $this->id             = $id;
        $this->datetime       = $datetime;
        $this->expirationDate = $expirationDate;
        $this->reason         = $reason;
    }
}
