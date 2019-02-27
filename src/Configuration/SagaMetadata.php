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
 * @property-read string $sagaClass
 * @property-read string $identifierClass
 * @property-read string $containingIdentifierSource
 * @property-read string $containingIdentifierProperty
 * @property-read string $expireDateModifier
 */
final class SagaMetadata
{
    public const DEFAULT_EXPIRE_INTERVAL     = '+1 hour';

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
     *
     * @var string
     */
    public $sagaClass;

    /**
     * Identifier class.
     *
     * @psalm-var class-string<\ServiceBus\Sagas\SagaId>
     *
     * @var string
     */
    public $identifierClass;

    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @var string
     */
    public $containingIdentifierSource;

    /**
     * The field that contains the saga identifier.
     *
     * @var string
     */
    public $containingIdentifierProperty;

    /**
     * Saga expire date modifier.
     *
     * @see http://php.net/manual/ru/datetime.formats.relative.php
     *
     * @var string
     */
    public $expireDateModifier;

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $identifierClass
     *
     * @param string $sagaClass
     * @param string $identifierClass
     * @param string $containingIdentifierSource
     * @param string $containingIdentifierProperty
     * @param string $expireDateModifier
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     *
     */
    public static function create(
        string $sagaClass,
        string $identifierClass,
        string $containingIdentifierSource,
        string $containingIdentifierProperty,
        string $expireDateModifier
    ): self {
        return new self($sagaClass, $identifierClass, $containingIdentifierSource, $containingIdentifierProperty, $expireDateModifier);
    }

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $identifierClass
     *
     * @param string $sagaClass
     * @param string $identifierClass
     * @param string $containingIdentifierSource
     * @param string $containingIdentifierProperty
     * @param string $expireDateModifier
     *
     * @throws \InvalidArgumentException
     */
    private function __construct(
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
