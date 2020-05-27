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
     * Date of creation.
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * Date of expiration.
     *
     * @var \DateTimeImmutable
     */
    public $expirationDate;

    public function __construct(SagaId $sagaId, \DateTimeImmutable $dateTime, \DateTimeImmutable $expirationDate)
    {
        $this->id             = $sagaId->toString();
        $this->idClass        = (string) \get_class($sagaId);
        $this->sagaClass      = $sagaId->sagaClass;
        $this->datetime       = $dateTime;
        $this->expirationDate = $expirationDate;
    }
}
