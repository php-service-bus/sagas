<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration\Annotations;

/**
 * Saga listener marker
 *
 * @Annotation
 * @Target("METHOD")
 *
 * @property-read string|null $containingIdProperty
 */
final class SagaEventListener
{
    /**
     * The event property that contains the saga ID
     * In the context of executing the handler, it overrides the value set for the saga globally
     *
     * @var string|null
     */
    public $containingIdProperty;

    /**
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data)
    {
        /** @var string|null $value */
        foreach($data as $key => $value)
        {
            if(false === \property_exists($this, $key))
            {
                throw new \InvalidArgumentException(
                    \sprintf('Unknown property "%s" on annotation "%s"', $key, \get_class($this))
                );
            }

            $this->{$key} = $value;
        }
    }

    /**
     * Has specified event property that contains the saga ID
     *
     * @return bool
     */
    public function hasContainingIdProperty(): bool
    {
        return null !== $this->containingIdProperty && '' !== $this->containingIdProperty;
    }
}
