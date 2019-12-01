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

/**
 * Basic information about saga.
 *
 * @psalm-readonly
 */
final class SagaMetadata
{
    public const DEFAULT_EXPIRE_INTERVAL = '+1 hour';

    public const CORRELATION_ID_SOURCE_EVENT = 'event';

    public const CORRELATION_ID_SOURCE_HEADERS = 'headers';

    private const CORRELATION_ID_SOURCES = [
        self::CORRELATION_ID_SOURCE_EVENT,
        self::CORRELATION_ID_SOURCE_HEADERS,
    ];

    /**
     * Class namespace.
     *
     * @psalm-var class-string<\ServiceBus\Sagas\Saga>
     */
    public string $sagaClass;

    /**
     * Identifier class.
     *
     * @psalm-var class-string<\ServiceBus\Sagas\SagaId>
     */
    public string $identifierClass;

    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     */
    public string $containingIdentifierSource;

    /**
     * The field that contains the saga identifier.
     */
    public string $containingIdentifierProperty;

    /**
     * Saga expire date modifier.
     *
     * @see http://php.net/manual/ru/datetime.formats.relative.php
     */
    public string $expireDateModifier;

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $identifierClass
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $sagaClass,
        string $identifierClass,
        string $containingIdentifierSource,
        string $containingIdentifierProperty,
        string $expireDateModifier
    ) {
        if (false === \in_array($containingIdentifierSource, self::CORRELATION_ID_SOURCES, true))
        {
            throw new \InvalidArgumentException(
                \sprintf(
                    'In the meta data of the saga "%s" an incorrect value of the "containingIdentifierSource" (can be `event` or `headers` only)',
                    $sagaClass
                )
            );
        }

        $this->sagaClass                    = $sagaClass;
        $this->identifierClass              = $identifierClass;
        $this->containingIdentifierSource   = $containingIdentifierSource;
        $this->containingIdentifierProperty = $containingIdentifierProperty;
        $this->expireDateModifier           = $expireDateModifier;
    }
}
