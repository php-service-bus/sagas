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
use function ServiceBus\Common\datetimeInstantiator;

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
    public string $id;

    /**
     * Saga identifier class.
     *
     * @var string
     */
    public string $idClass;

    /**
     * Saga class.
     *
     * @var string
     */
    public string $sagaClass;

    /**
     * Previous saga status.
     *
     * @var string
     */
    public string $previousStatus;

    /**
     * Previous saga status.
     *
     * @var string
     */
    public string $newStatus;

    /**
     * Reason for changing the status of the saga.
     *
     * @var string|null
     */
    public ?string $withReason = null;

    /**
     * Operation datetime.
     *
     * @var \DateTimeImmutable
     */
    public \DateTimeImmutable $datetime;

    public function __construct(
        SagaId $sagaId,
        SagaStatus $currentStatus,
        SagaStatus $newStatus,
        ?string $withReason = null
    ) {
        /**
         * @noinspection PhpUnhandledExceptionInspection
         *
         * @var \DateTimeImmutable $datetime
         */
        $datetime = datetimeInstantiator('NOW');

        $this->id             = $sagaId->toString();
        $this->idClass        = (string) \get_class($sagaId);
        $this->sagaClass      = $sagaId->sagaClass;
        $this->previousStatus = $currentStatus->toString();
        $this->newStatus      = $newStatus->toString();
        $this->withReason     = $withReason;
        $this->datetime       = $datetime;
    }
}
