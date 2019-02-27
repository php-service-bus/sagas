<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

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
     * @param SagaId $id
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while deserializing saga
     *
     * @return Promise<\ServiceBus\Sagas\Saga|null>
     *
     */
    public function obtain(SagaId $id): Promise;

    /**
     * Save the new saga.
     *
     * @param Saga $saga
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga The specified saga has already been added
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     *
     * @return Promise It doesn't return any result
     *
     */
    public function save(Saga $saga): Promise;

    /**
     * Update existing saga.
     *
     * @param Saga $saga
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     *
     * @return Promise It doesn't return any result
     *
     */
    public function update(Saga $saga): Promise;

    /**
     * Remove saga from database.
     *
     * @param SagaId $id
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     *
     * @return Promise It doesn't return any result
     *
     */
    public function remove(SagaId $id): Promise;
}
