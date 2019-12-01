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
use function ServiceBus\Common\datetimeInstantiator;

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
     * Reason for closing the saga.
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

    public function __construct(SagaId $sagaId, ?string $withReason = null)
    {
        /**
         * @noinspection PhpUnhandledExceptionInspection
         *
         * @var \DateTimeImmutable $datetime
         */
        $datetime = datetimeInstantiator('NOW');

        $this->id         = $sagaId->toString();
        $this->idClass    = (string) \get_class($sagaId);
        $this->sagaClass  = $sagaId->sagaClass;
        $this->withReason = $withReason;
        $this->datetime   = $datetime;
    }
}
