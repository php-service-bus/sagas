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

use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\SagaStatus;

/**
 * The status of the saga was changed.
 *
 * @psalm-immutable
 */
final class SagaStatusChanged
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
     * Saga identifier class.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $idClass;

    /**
     * Saga class.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $sagaClass;

    /**
     * Previous saga status.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $previousStatus;

    /**
     * Previous saga status.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $newStatus;

    /**
     * Reason for changing the status of the saga.
     *
     * @psalm-readonly
     *
     * @var string|null
     */
    public $withReason;

    /**
     * Operation datetime.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    public function __construct(
        SagaId $sagaId,
        SagaStatus $currentStatus,
        SagaStatus $newStatus,
        \DateTimeImmutable $datetime,
        ?string $withReason = null
    ) {
        $this->id             = $sagaId->toString();
        $this->idClass        = \get_class($sagaId);
        $this->sagaClass      = $sagaId->sagaClass;
        $this->previousStatus = $currentStatus->toString();
        $this->newStatus      = $newStatus->toString();
        $this->withReason     = $withReason;
        $this->datetime       = $datetime;
    }
}
