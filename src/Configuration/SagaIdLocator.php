<?php

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

use Amp\Promise;
use ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions;
use ServiceBus\Sagas\Configuration\Metadata\SagaMetadata;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\SagasStore;
use function Amp\call;
use function ServiceBus\Common\readReflectionPropertyValue;

final class SagaIdLocator
{
    /**
     * @var SagasStore
     */
    private $sagaStore;

    public function __construct(SagasStore $sagaStore)
    {
        $this->sagaStore = $sagaStore;
    }

    /**
     * Finding a saga id in an incoming packet.
     *
     * @psalm-return Promise<\ServiceBus\Sagas\SagaId>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    public function process(
        SagaHandlerOptions $handlerOptions,
        object             $message,
        array              $headers
    ): Promise
    {
        return call(
            function() use ($handlerOptions, $message, $headers): \Generator
            {
                $propertyName  = $handlerOptions->containingIdentifierProperty();
                $propertyValue = match ($handlerOptions->containingIdentifierSource())
                {
                    SagaMetadata::CORRELATION_ID_SOURCE_HEADERS => !empty($headers[$propertyName])
                        ? (string) $headers[$propertyName]
                        : throw InvalidSagaIdentifier::headerKeyCantBeEmpty($propertyName),
                    SagaMetadata::CORRELATION_ID_SOURCE_MESSAGE => $this->readMessagePropertyValue(
                        $message,
                        $propertyName
                    ),
                    default => throw new InvalidSagaIdentifier("Unsupported source type")
                };

                /**
                 * Let's try to find the saga identifier using the association.
                 *
                 * @var SagaId|null $sagaId
                 */
                $sagaId = yield $this->sagaStore->searchIdByAssociatedProperty(
                    sagaClass: $handlerOptions->sagaClass(),
                    idClass: $handlerOptions->identifierClass(),
                    propertyKey: $propertyName,
                    propertyValue: $propertyValue
                );

                return $sagaId ?? $this->identifierInstantiator(
                        idClass: $handlerOptions->identifierClass(),
                        idValue: $propertyValue,
                        sagaClass: $handlerOptions->sagaClass(),
                    );
            }
        );
    }

    /**
     * @psalm-param non-empty-string $propertyName
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function readMessagePropertyValue(object $message, string $propertyName): string
    {
        try
        {
            /** @psalm-var object|string|int|float $value */
            $value = $message->{$propertyName} ?? readReflectionPropertyValue($message, $propertyName);
        }
        catch(\Throwable)
        {
            throw InvalidSagaIdentifier::propertyNotFound($propertyName, $message);
        }

        if(\is_string($value) && $value !== '')
        {
            return $value;
        }

        if(\is_object($value) && \method_exists($value, 'toString'))
        {
            $value = (string) $value->toString();

            if($value !== '')
            {
                return $value;
            }
        }

        throw InvalidSagaIdentifier::propertyCantBeEmpty($propertyName, $message);
    }

    /**
     * Create identifier instance.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $idClass
     * @psalm-param class-string<\ServiceBus\Sagas\Saga>   $sagaClass
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function identifierInstantiator(
        string $idClass,
        string $idValue,
        string $sagaClass
    ): SagaId
    {
        /** @var object|SagaId $identifier */
        $identifier = new $idClass($idValue, $sagaClass);

        if($identifier instanceof SagaId)
        {
            return $identifier;
        }

        throw InvalidSagaIdentifier::wrongType($identifier);
    }
}
