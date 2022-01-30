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

namespace ServiceBus\Sagas\Configuration\Attributes;

/**
 * @psalm-immutable
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class SagaInitialHandler
{
    /**
     * Place to look for a correlation identifier (command property: command; header key: headers).
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    public $containingIdSource;

    /**
     * The command property that contains the saga ID
     * In the context of executing the handler, it overrides the value set for the saga globally.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
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

    public function __construct(
        ?string $containingIdSource = null,
        ?string $containingIdProperty = null,
        ?string $description = null
    ) {
        $this->containingIdSource   = !empty($containingIdSource) ? $containingIdSource : null;
        $this->containingIdProperty = !empty($containingIdProperty) ? $containingIdProperty : null;
        $this->description          = !empty($description) ? $description : null;
    }

    /**
     * Has specified command property that contains the saga ID.
     */
    public function hasContainingIdProperty(): bool
    {
        return $this->containingIdProperty !== null;
    }
}
