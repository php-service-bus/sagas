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
use function ServiceBus\Common\now;

/**
 * The saga was completed.
 *
 * @psalm-readonly
 */
final class SagaClosed
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
     * Reason for closing the saga.
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

    public function __construct(SagaId $sagaId, ?string $withReason = null)
    {
        $this->id         = $sagaId->toString();
        $this->idClass    = (string) \get_class($sagaId);
        $this->sagaClass  = $sagaId->sagaClass;
        $this->withReason = $withReason;
        $this->datetime   = now();
    }
}
