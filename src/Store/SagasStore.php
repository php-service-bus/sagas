<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
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
 * Interface to work with the storage of sagas
 */
interface SagasStore
{
    /**
     * Obtain exists saga
     *
     * @param SagaId $id
     * @param bool   $ignoreExpiration If specified, the expiration date will be ignored (the saga will be in read-only mode);
     *                                 Otherwise, when trying to load an expired saga, an exception will be thrown
     *
     * @return Promise<\ServiceBus\Sagas\Saga|null>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while deserializing saga
     * @throws \ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga
     */
    public function obtain(SagaId $id, bool $ignoreExpiration): Promise;

    /**
     * Save the new saga
     *
     * @param Saga $saga
     *
     * @return Promise It doesn't return any result
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga The specified saga has already been added
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     */
    public function save(Saga $saga): Promise;

    /**
     * Update existing saga
     *
     * @param Saga $saga
     *
     * @return Promise It doesn't return any result
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     */
    public function update(Saga $saga): Promise;

    /**
     * Remove saga from database
     *
     * @param SagaId $id
     *
     * @return Promise It doesn't return any result
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     */
    public function remove(SagaId $id): Promise;
}
