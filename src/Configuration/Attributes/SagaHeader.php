<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Configuration\Attributes;

/**
 * @psalm-immutable
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SagaHeader
{
    /**
     * Saga identifier class.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\Sagas\SagaId>
     *
     * @var string
     */
    public $idClass;

    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $containingIdSource;

    /**
     * The event property (or header key) that contains the saga ID.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $containingIdProperty;

    /**
     * Saga expire date modifier.
     *
     * @psalm-readonly
     *
     * @see http://php.net/manual/ru/datetime.formats.relative.php
     *
     * @var string|null
     */
    public $expireDateModifier;

    public function __construct(
        string $idClass,
        string $containingIdProperty,
        string $containingIdSource = 'event',
        ?string $expireDateModifier = null
    ) {
        $this->idClass              = $idClass;
        $this->containingIdProperty = $containingIdProperty;
        $this->containingIdSource   = $containingIdSource;
        $this->expireDateModifier   = $expireDateModifier;
    }
}
