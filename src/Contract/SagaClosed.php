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
 * The saga was completed.
 *
 * @property-read string             $id
 * @property-read string             $idClass
 * @property-read string             $sagaClass
 * @property-read string|null        $withReason
 * @property-read \DateTimeImmutable $datetime
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
    public $withReason;

    /**
     * Operation datetime.
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param SagaId      $sagaId
     * @param string|null $withReason
     *
     * @return self
     */
    public static function create(SagaId $sagaId, ?string $withReason = null): self
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new self(
            (string) $sagaId,
            \get_class($sagaId),
            $sagaId->sagaClass,
            $withReason,
            new \DateTimeImmutable('NOW')
        );
    }

    /**
     * @param string             $id
     * @param string             $idClass
     * @param string             $sagaClass
     * @param string|null        $withReason
     * @param \DateTimeImmutable $datetime
     */
    private function __construct(string $id, string $idClass, string $sagaClass, ?string $withReason, \DateTimeImmutable $datetime)
    {
        $this->id         = $id;
        $this->idClass    = $idClass;
        $this->sagaClass  = $sagaClass;
        $this->withReason = $withReason;
        $this->datetime   = $datetime;
    }
}
