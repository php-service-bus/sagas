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
 * @psalm-readonly
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
    public $withReason = null;

    /**
     * Operation datetime.
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
        $this->idClass        = (string) \get_class($sagaId);
        $this->sagaClass      = $sagaId->sagaClass;
        $this->previousStatus = $currentStatus->toString();
        $this->newStatus      = $newStatus->toString();
        $this->withReason     = $withReason;
        $this->datetime       = $datetime;
    }
}
