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
 * @property-read string             $id
 * @property-read string             $idClass
 * @property-read string             $sagaClass
 * @property-read \DateTimeImmutable $datetime
 * @property-read \DateTimeImmutable $expirationDate
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
        return new self($sagaId->toString(), \get_class($sagaId), $sagaId->sagaClass, $dateTime, $expirationDate);
    }

    /**
     * @param string             $id
     * @param string             $idClass
     * @param string             $sagaClass
     * @param \DateTimeImmutable $datetime
     * @param \DateTimeImmutable $expirationDate
     */
    private function __construct(
        string $id,
        string $idClass,
        string $sagaClass,
        \DateTimeImmutable $datetime,
        \DateTimeImmutable $expirationDate
    ) {
        $this->id             = $id;
        $this->idClass        = $idClass;
        $this->sagaClass      = $sagaClass;
        $this->datetime       = $datetime;
        $this->expirationDate = $expirationDate;
    }
}
