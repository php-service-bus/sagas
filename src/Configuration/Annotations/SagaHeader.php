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
 * @property-read string|null $idClass
 * @property-read string|null $containingIdentifierSource
 * @property-read string|null $containingIdProperty
 * @property-read string|null $expireDateModifier
 */
final class SagaHeader
{
    /**
     * Saga identifier class.
     *
     * @psalm-var class-string<\ServiceBus\Sagas\SagaId>|null
     *
     * @var string|null
     */
    public $idClass;

    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @var string|null
     */
    public $containingIdSource;

    /**
     * The event property (or header key) that contains the saga ID.
     *
     * @var string|null
     */
    public $containingIdProperty;

    /**
     * Saga expire date modifier.
     *
     * @see http://php.net/manual/ru/datetime.formats.relative.php
     *
     * @var string|null
     */
    public $expireDateModifier;

    /**
     * @psalm-param array<string, string|null> $data
     *
     * @param array $data
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
     *
     * @return bool
     */
    public function hasSpecifiedExpireDateModifier(): bool
    {
        return null !== $this->expireDateModifier && '' !== $this->expireDateModifier;
    }

    /**
     * Has specified event property that contains the saga ID.
     *
     * @return bool
     */
    public function hasContainingIdProperty(): bool
    {
        return null !== $this->containingIdProperty && '' !== $this->containingIdProperty;
    }
}
