<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Store;

use Amp\Promise;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;

/**
 * Interface to work with the storage of sagas.
 */
interface SagasStore
{
    /**
     * Obtain exists saga.
     *
     * @psalm-return Promise<\ServiceBus\Sagas\Saga|null>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while deserializing saga
     */
    public function obtain(SagaId $id): Promise;

    /**
     * @psalm-param callable():\Generator $publisher
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga The specified saga has already been added
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     */
    public function save(Saga $saga, callable $publisher): Promise;

    /**
     * Update existing saga.
     *
     * @psalm-param callable():\Generator $publisher
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     */
    public function update(Saga $saga, callable $publisher): Promise;
}
