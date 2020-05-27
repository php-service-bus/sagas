<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas;

use function ServiceBus\Common\uuid;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;

/**
 * Base saga id class.
 */
abstract class SagaId
{
    /**
     * Identifier.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $id;

    /**
     * Saga class.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\Sagas\Saga>
     *
     * @var string
     */
    public $sagaClass;

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    public static function new(string $sagaClass): self
    {
        return new static(uuid(), $sagaClass);
    }

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga>|string $sagaClass
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    final public function __construct(string $id, string $sagaClass)
    {
        if ($id === '')
        {
            throw InvalidSagaIdentifier::idValueCantBeEmpty();
        }

        if (
            $sagaClass === '' ||
            \class_exists($sagaClass) === false ||
            \is_a($sagaClass, Saga::class, true) === false
        ) {
            throw InvalidSagaIdentifier::invalidSagaClass($sagaClass);
        }

        /** @psalm-var class-string<\ServiceBus\Sagas\Saga> $sagaClass */

        $this->id        = $id;
        $this->sagaClass = $sagaClass;
    }

    final public function toString(): string
    {
        return $this->id;
    }

    public function equals(SagaId $id): bool
    {
        return $this->id === $id->id;
    }
}
