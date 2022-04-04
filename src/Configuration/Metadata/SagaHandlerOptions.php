<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration\Metadata;

use ServiceBus\Common\MessageHandler\MessageHandlerOptions;

/**
 * Specified for each listener/handler options.
 */
final class SagaHandlerOptions implements MessageHandlerOptions
{
    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @var string|null
     */
    private $containingIdentifierSource;

    /**
     * If a value is specified for a particular listener, then it will be used. Otherwise, the value will be obtained
     * from the global parameters of the saga.
     *
     * @var string|null
     */
    private $containingIdentifierProperty;

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
        $self->containingIdentifierProperty = \lcfirst($containingIdentifierProperty);
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
     *
     * @psalm-return non-empty-string
     */
    public function containingIdentifierProperty(): string
    {
        $containingIdentifierProperty = $this->containingIdentifierProperty;

        if ($containingIdentifierProperty !== null && $containingIdentifierProperty !== '')
        {
            return $containingIdentifierProperty;
        }

        return $this->sagaMetadata->containingIdentifierProperty;
    }

    /**
     * Receive place to look for a correlation identifier.
     */
    public function containingIdentifierSource(): string
    {
        $containingIdentifierSource = $this->containingIdentifierSource;

        if ($containingIdentifierSource !== null && $containingIdentifierSource !== '')
        {
            return $containingIdentifierSource;
        }

        return $this->sagaMetadata->containingIdentifierSource;
    }

    private function __construct(SagaMetadata $sagaMetadata)
    {
        $this->sagaMetadata = $sagaMetadata;
    }
}
