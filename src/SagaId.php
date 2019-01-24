<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
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
 * Base saga id class
 *
 * @property-read string $id
 * @property-read string $sagaClass
 */
abstract class SagaId
{
    /**
     * Identifier
     *
     * @var string
     */
    public $id;

    /**
     * Saga class
     *
     * @psalm-var class-string<\ServiceBus\Sagas\Saga>
     *
     * @var string
     */
    public $sagaClass;

    /**
     * @param string $sagaClass
     *
     * @return static
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    public static function new(string $sagaClass): self
    {
        /** @psalm-var class-string<\ServiceBus\Sagas\Saga> $sagaClass */

        return new static(uuid(), $sagaClass);
    }

    /**
     * @param string $id
     * @param string $sagaClass
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    final public function __construct(string $id, string $sagaClass)
    {
        if('' === $id)
        {
            throw new InvalidSagaIdentifier('The saga identifier can\'t be empty');
        }

        if('' === $sagaClass || false === \class_exists($sagaClass))
        {
            throw new InvalidSagaIdentifier(
                \sprintf('Invalid saga class specified ("%s")', $sagaClass)
            );
        }

        /** @psalm-var class-string<\ServiceBus\Sagas\Saga> $sagaClass */

        $this->id        = $id;
        $this->sagaClass = $sagaClass;
    }

    /**
     * @return string
     */
    final public function __toString(): string
    {
        return $this->id;
    }

    /**
     * @param SagaId $id
     *
     * @return bool
     */
    public function equals(SagaId $id): bool
    {
        return $this->id === (string) $id;
    }
}
