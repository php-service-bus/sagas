<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration\Attributes;

/**
 * @psalm-immutable
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class SagaEventListener
{
    /**
     * Place to look for a correlation identifier (event property: event; header key: headers).
     *
     * @psalm-readonly
     *
     * @var string|null
     */
    public $containingIdSource;

    /**
     * The event property that contains the saga ID
     * In the context of executing the handler, it overrides the value set for the saga globally.
     *
     * @psalm-readonly
     *
     * @var string|null
     */
    public $containingIdProperty;

    /**
     * Message description.
     * Will be added to the log when the method is called.
     *
     * @psalm-readonly
     *
     * @var string|null
     */
    public $description;

    /**
     * @psalm-param non-empty-string|null $containingIdSource
     * @psalm-param non-empty-string|null $containingIdProperty
     */
    public function __construct(
        ?string $containingIdSource = null,
        ?string $containingIdProperty = null,
        ?string $description = null
    ) {
        $this->containingIdSource   = $containingIdSource;
        $this->containingIdProperty = $containingIdProperty;
        $this->description          = $description;
    }

    /**
     * Has specified event property that contains the saga ID.
     */
    public function hasContainingIdProperty(): bool
    {
        return $this->containingIdProperty !== null && $this->containingIdProperty !== '';
    }
}
