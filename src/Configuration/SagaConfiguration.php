<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Configuration;

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
    public $metaData;

    /**
     * @psalm-readonly
     *
     * @var \SplObjectStorage
     */
    public $handlerCollection;

    public function __construct(SagaMetadata $sagaMetadata, \SplObjectStorage $handlerCollection)
    {
        $this->metaData          = $sagaMetadata;
        $this->handlerCollection = $handlerCollection;
    }
}
