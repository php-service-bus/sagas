<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Exceptions;

use ServiceBus\Sagas\SagaStatus;

/**
 *
 */
final class ChangeSagaStateFailed extends \RuntimeException
{
    /**
     * @param SagaStatus $currentStatus
     *
     * @return self
     */
    public static function create(SagaStatus $currentStatus): self
    {
        return new self(
            \sprintf('Changing the state of the saga is impossible: the saga is complete with status "%s"', $currentStatus)
        );
    }
}
