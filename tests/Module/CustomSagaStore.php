<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Module;

use Amp\Promise;
use Amp\Success;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\SagasStore;

/**
 *
 */
final class CustomSagaStore implements SagasStore
{
    public function obtain(SagaId $id): Promise
    {
        return new Success();
    }

    public function save(Saga $saga): Promise
    {
        return new Success();
    }

    public function update(Saga $saga): Promise
    {
        return new Success();
    }

    public function remove(SagaId $id): Promise
    {
        return new Success();
    }
}
