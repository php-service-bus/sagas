<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Contract;

use ServiceBus\Sagas\SagaId;

/**
 * The saga was completed.
 *
 * @psalm-immutable
 */
final class SagaClosed
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
     * Reason for closing the saga.
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

    public function __construct(SagaId $sagaId, \DateTimeImmutable $datetime, ?string $withReason = null)
    {
        $this->id         = $sagaId->toString();
        $this->idClass    = \get_class($sagaId);
        $this->sagaClass  = $sagaId->sagaClass;
        $this->withReason = $withReason;
        $this->datetime   = $datetime;
    }
}
