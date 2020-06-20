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

use ServiceBus\Common\MessageHandler\MessageHandlerOptions;

/**
 * Specified for each listener options.
 */
final class SagaListenerOptions implements MessageHandlerOptions
{
    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @var string|null
     */
    private $containingIdentifierSource = null;

    /**
     * If a value is specified for a particular listener, then it will be used. Otherwise, the value will be obtained
     * from the global parameters of the saga.
     *
     * @var string|null
     */
    private $containingIdentifierProperty = null;

    /**
     * Basic information about saga.
     *
     * @var SagaMetadata
     */
    private $sagaMetadata;

    /**
     * Listener description
     *
     * @var string|null
     */
    private $description;

    public static function withCustomContainingIdentifierProperty(
        string $containingIdentifierSource,
        string $containingIdentifierProperty,
        SagaMetadata $metadata,
        ?string $description
    ): self {
        $self = new self($metadata);

        $self->containingIdentifierSource   = $containingIdentifierSource;
        $self->containingIdentifierProperty = $containingIdentifierProperty;
        $self->description                  = $description;

        return $self;
    }

    public static function withGlobalOptions(SagaMetadata $metadata, ?string $description): self
    {
        $self = new self($metadata);

        $self->description = $description;

        return $self;
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

    public function description(): ?string
    {
        return $this->description;
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
