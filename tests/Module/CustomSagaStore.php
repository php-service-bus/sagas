<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
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
    /**
     * {@inheritdoc}
     */
    public function obtain(SagaId $id): Promise
    {
        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Saga $saga): Promise
    {
        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function update(Saga $saga): Promise
    {
        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function remove(SagaId $id): Promise
    {
        return new Success();
    }
}
