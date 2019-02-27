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

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param SagaId             $sagaId
     * @param \DateTimeImmutable $dateTime
     * @param \DateTimeImmutable $expirationDate
     *
     * @return self
     */
    public static function create(SagaId $sagaId, \DateTimeImmutable $dateTime, \DateTimeImmutable $expirationDate): self
    {
        $self = new self();

        $self->id             = (string) $sagaId;
        $self->idClass        = \get_class($sagaId);
        $self->sagaClass      = $sagaId->sagaClass;
        $self->datetime       = $dateTime;
        $self->expirationDate = $expirationDate;

        return $self;
    }
}
