<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Exceptions;

/**
 *
 */
final class SagaMetaDataNotFound extends \RuntimeException
{
    public static function create(string $sagaClass): self
    {
        return new self(
            \sprintf(
                'Meta data of the saga "%s" not found. The saga was not configured',
                $sagaClass
            )
        );
    }
}
