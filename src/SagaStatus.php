<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas;

/**
 * SagaStatus of the saga.
 */
enum SagaStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case REOPENED = 'reopened';

    public function equals(SagaStatus $with): bool
    {
        return $this->value === $with->value;
    }
}
