<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Exceptions;

use ServiceBus\Sagas\Saga;

/**
 *
 */
final class CantSaveUnStartedSaga extends \LogicException
{
    public static function create(Saga $saga): self
    {
        return new self(
            \sprintf(
                'Saga with identifier "%s:%s" not exists. Please, use start() method for saga creation',
                $saga->id()->toString(),
                \get_class($saga->id())
            )
        );
    }
}
