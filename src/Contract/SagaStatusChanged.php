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

use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\SagaStatus;

/**
 * The status of the saga was changed.
 *
 * @property-read string             $id
 * @property-read string             $idClass
 * @property-read string             $sagaClass
 * @property-read string             $previousStatus
 * @property-read string             $newStatus
 * @property-read string|null        $withReason
 * @property-read \DateTimeImmutable $datetime
 */
final class SagaStatusChanged
{
    /**
     * Saga identifier.
     *
     * @var string
     */
    public $id;

    /**
     * Saga identifier class.
     *
     * @var string
     */
    public $idClass;

    /**
     * Saga class.
     *
     * @var string
     */
    public $sagaClass;

    /**
     * Previous saga status.
     *
     * @var string
     */
    public $previousStatus;

    /**
     * Previous saga status.
     *
     * @var string
     */
    public $newStatus;

    /**
     * Reason for changing the status of the saga.
     *
     * @var string|null
     */
    public $withReason;

    /**
     * Operation datetime.
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param SagaId      $sagaId
     * @param SagaStatus  $currentStatus
     * @param SagaStatus  $newStatus
     * @param string|null $withReason
     *
     * @return self
     */
    public static function create(
        SagaId $sagaId,
        SagaStatus $currentStatus,
        SagaStatus $newStatus,
        ?string $withReason = null
    ): self {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new self(
            (string) $sagaId,
            \get_class($sagaId),
            $sagaId->sagaClass,
            (string) $currentStatus,
            (string) $newStatus,
            $withReason,
            new \DateTimeImmutable('NOW')
        );
    }

    /**
     * @param string             $id
     * @param string             $idClass
     * @param string             $sagaClass
     * @param string             $previousStatus
     * @param string             $newStatus
     * @param string|null        $withReason
     * @param \DateTimeImmutable $datetime
     */
    private function __construct(
        string $id,
        string $idClass,
        string $sagaClass,
        string $previousStatus,
        string $newStatus,
        ?string $withReason,
        \DateTimeImmutable $datetime
    ) {
        $this->id             = $id;
        $this->idClass        = $idClass;
        $this->sagaClass      = $sagaClass;
        $this->previousStatus = $previousStatus;
        $this->newStatus      = $newStatus;
        $this->withReason     = $withReason;
        $this->datetime       = $datetime;
    }
}
