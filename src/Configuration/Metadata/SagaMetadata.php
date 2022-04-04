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

/**
 * Basic information about saga.
 *
 * @psalm-immutable
 */
final class SagaMetadata
{
    public const DEFAULT_EXPIRE_INTERVAL = '+1 hour';

    public const CORRELATION_ID_SOURCE_MESSAGE = 'message';

    public const CORRELATION_ID_SOURCE_HEADERS = 'headers';

    private const CORRELATION_ID_SOURCES = [
        self::CORRELATION_ID_SOURCE_MESSAGE,
        self::CORRELATION_ID_SOURCE_HEADERS,
    ];

    /**
     * Class namespace.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\Sagas\Saga>
     *
     * @var string
     */
    public $sagaClass;

    /**
     * Identifier class.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\Sagas\SagaId>
     *
     * @var string
     */
    public $identifierClass;

    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $containingIdentifierSource;

    /**
     * The field that contains the saga identifier.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $containingIdentifierProperty;

    /**
     * Saga expire date modifier.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @see http://php.net/manual/ru/datetime.formats.relative.php
     *
     * @var string
     */
    public $expireDateModifier;

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga>   $sagaClass
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $identifierClass
     * @psalm-param non-empty-string                       $containingIdentifierSource
     * @psalm-param non-empty-string                       $containingIdentifierProperty
     * @psalm-param non-empty-string                       $expireDateModifier
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
        if (\in_array($containingIdentifierSource, self::CORRELATION_ID_SOURCES, true) === false)
        {
            throw new \InvalidArgumentException(
                \sprintf(
                    'In the metadata of the saga "%s" an incorrect value of the "containingIdentifierSource" (can be `message` or `headers` only)',
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
