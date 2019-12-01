<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration\Annotations;

/**
 * @Annotation
 * @Target("CLASS")
 *
 * @psalm-readonly
 */
final class SagaHeader
{
    /**
     * Saga identifier class.
     *
     * @psalm-var class-string<\ServiceBus\Sagas\SagaId>|null
     */
    public ?string $idClass = null;

    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     */
    public ?string $containingIdSource = null;

    /**
     * The event property (or header key) that contains the saga ID.
     */
    public ?string $containingIdProperty = null;

    /**
     * Saga expire date modifier.
     *
     * @see http://php.net/manual/ru/datetime.formats.relative.php
     */
    public ?string $expireDateModifier = null;

    /**
     * @psalm-param array<string, string|null> $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data)
    {
        /** @var string|null $value */
        foreach ($data as $key => $value)
        {
            if (false === \property_exists($this, $key))
            {
                throw new \InvalidArgumentException(
                    \sprintf('Unknown property "%s" on annotation "%s"', $key, \get_class($this))
                );
            }

            $this->{$key} = $value;
        }
    }

    /**
     * Has specified expire date interval.
     */
    public function hasSpecifiedExpireDateModifier(): bool
    {
        return null !== $this->expireDateModifier && '' !== $this->expireDateModifier;
    }

    /**
     * Has specified event property that contains the saga ID.
     */
    public function hasContainingIdProperty(): bool
    {
        return null !== $this->containingIdProperty && '' !== $this->containingIdProperty;
    }
}
