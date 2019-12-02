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
 * Configuration details.
 *
 * @psalm-readonly
 */
final class SagaConfiguration
{
    public SagaMetadata $metaData;

    public \SplObjectStorage $handlerCollection;

    public function __construct(SagaMetadata $metaData, \SplObjectStorage $handlerCollection)
    {
        $this->metaData          = $metaData;
        $this->handlerCollection = $handlerCollection;
    }
}
