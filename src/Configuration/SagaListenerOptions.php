<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

use ServiceBus\Common\MessageExecutor\MessageHandlerOptions;

/**
 * Specified for each listener options.
 */
final class SagaListenerOptions implements MessageHandlerOptions
{
    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     */
    private ?string $containingIdentifierSource = null;

    /**
     * If a value is specified for a particular listener, then it will be used. Otherwise, the value will be obtained
     * from the global parameters of the saga.
     */
    private ?string $containingIdentifierProperty= null;

    /**
     * Basic information about saga.
     */
    private SagaMetadata $sagaMetadata;

    public static function withCustomContainingIdentifierProperty(
        string $containingIdentifierSource,
        string $containingIdentifierProperty,
        SagaMetadata $metadata
    ): self {
        $self = new self($metadata);

        $self->containingIdentifierSource   = $containingIdentifierSource;
        $self->containingIdentifierProperty = $containingIdentifierProperty;

        return $self;
    }

    public static function withGlobalOptions(SagaMetadata $metadata): self
    {
        return new self($metadata);
    }

    /**
     * Receive saga class.
     *
     * @psalm-return class-string<\ServiceBus\Sagas\Saga>
     */
    public function sagaClass(): string
    {
        return $this->sagaMetadata->sagaClass;
    }

    /**
     * Receive identifier class.
     *
     * @psalm-return class-string<\ServiceBus\Sagas\SagaId>
     */
    public function identifierClass(): string
    {
        return $this->sagaMetadata->identifierClass;
    }

    /**
     * Receive the name of the event property that contains the saga ID.
     */
    public function containingIdentifierProperty(): string
    {
        if (null !== $this->containingIdentifierProperty && '' !== $this->containingIdentifierProperty)
        {
            return (string) $this->containingIdentifierProperty;
        }

        return $this->sagaMetadata->containingIdentifierProperty;
    }

    /**
     * Receive place to look for a correlation identifier.
     */
    public function containingIdentifierSource(): string
    {
        if (null !== $this->containingIdentifierProperty && '' !== $this->containingIdentifierSource)
        {
            return (string) $this->containingIdentifierSource;
        }

        return $this->sagaMetadata->containingIdentifierSource;
    }

    private function __construct(SagaMetadata $sagaMetadata)
    {
        $this->sagaMetadata = $sagaMetadata;
    }
}
