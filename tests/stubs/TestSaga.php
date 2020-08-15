<?php

/**
 * Saga pattern implementation module.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Sagas\Configuration\Annotations\SagaEventListener;
use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;
use ServiceBus\Sagas\Saga;

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class TestSaga extends Saga
{
    /**
     * {@inheritdoc}
     */
    public function start(object $command): void
    {
    }

    /**
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    public function doSomething(): void
    {
        $this->fire(new EmptyCommand());
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener()
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    private function onEmptyEvent(/** @noinspection PhpUnusedParameterInspection */
        EmptyEvent $event
    ): void {
        $this->makeFailed('test reason');
    }
}
