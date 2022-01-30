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

use ServiceBus\Sagas\SagaStatus;

/**
 *
 */
final class ChangeSagaStateFailed extends \RuntimeException
{
    public static function create(SagaStatus $currentStatus): self
    {
        return new self(
            \sprintf(
                'Changing the state of the saga is impossible: the saga is complete with status "%s"',
                $currentStatus->value
            )
        );
    }

    public static function applyEventFailed(string $withReason): self
    {
        return new self($withReason);
    }
}
