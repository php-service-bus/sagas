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
 * New saga created.
 *
 * @psalm-immutable
 */
final class SagaCreated
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
     * Date of creation.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * Date of expiration.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $expirationDate;

    public function __construct(SagaId $sagaId, \DateTimeImmutable $dateTime, \DateTimeImmutable $expirationDate)
    {
        $this->id             = $sagaId->toString();
        $this->idClass        = \get_class($sagaId);
        $this->sagaClass      = $sagaId->sagaClass;
        $this->datetime       = $dateTime;
        $this->expirationDate = $expirationDate;
    }
}
