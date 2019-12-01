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

/**
 * New saga created.
 *
 * @psalm-readonly
 */
final class SagaCreated
{
    /**
     * Saga identifier.
     */
    public string $id;

    /**
     * Saga identifier class.
     */
    public string $idClass;

    /**
     * Saga class.
     */
    public string $sagaClass;

    /**
     * Date of creation.
     */
    public \DateTimeImmutable $datetime;

    /**
     * Date of expiration.
     */
    public \DateTimeImmutable $expirationDate;

    public function __construct(SagaId $sagaId, \DateTimeImmutable $dateTime, \DateTimeImmutable $expirationDate)
    {
        $this->id             = $sagaId->toString();
        $this->idClass        = (string) \get_class($sagaId);
        $this->sagaClass      = $sagaId->sagaClass;
        $this->datetime       = $dateTime;
        $this->expirationDate = $expirationDate;
    }
}
