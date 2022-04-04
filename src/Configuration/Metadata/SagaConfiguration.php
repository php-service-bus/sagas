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

use ServiceBus\Common\MessageHandler\MessageHandler;

/**
 * Configuration details.
 *
 * @psalm-immutable
 */
final class SagaConfiguration
{
    /**
     * @psalm-readonly
     *
     * @var SagaMetadata
     */
    public $metadata;

    /**
     * @psalm-readonly
     *
     * @var MessageHandler
     */
    public $initialCommandHandler;

    /**
     * @psalm-readonly
     *
     * @psalm-var \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler, null>
     */
    public $listenerCollection;

    /**
     * @psalm-param \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler, null> $listenerCollection
     */
    public function __construct(
        SagaMetadata      $metadata,
        MessageHandler    $initialCommandHandler,
        \SplObjectStorage $listenerCollection
    ) {
        $this->metadata              = $metadata;
        $this->initialCommandHandler = $initialCommandHandler;
        $this->listenerCollection    = $listenerCollection;
    }
}
