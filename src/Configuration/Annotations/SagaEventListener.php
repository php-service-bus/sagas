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
 * Saga listener marker.
 *
 * @Annotation
 * @Target("METHOD")
 *
 * @psalm-immutable
 */
final class SagaEventListener
{
    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @var string|null
     */
    public $containingIdSource = null;

    /**
     * The event property that contains the saga ID
     * In the context of executing the handler, it overrides the value set for the saga globally.
     *
     * @var string|null
     */
    public $containingIdProperty = null;

    /**
     * Message description.
     * Will be added to the log when the method is called.
     *
     * @var string|null
     */
    public $description = null;

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
     * Has specified event property that contains the saga ID.
     */
    public function hasContainingIdProperty(): bool
    {
        return null !== $this->containingIdProperty && '' !== $this->containingIdProperty;
    }
}
