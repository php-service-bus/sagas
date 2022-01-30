<?php

/** @noinspection PhpUnusedPrivateMethodInspection */

/**
 * Saga pattern implementation module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\MessagesRouter\Tests\stubs\TestCommand;
use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Configuration\Attributes\SagaInitialHandler;
use ServiceBus\Sagas\Saga;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class TestSaga extends Saga
{
    #[SagaInitialHandler]
    public function start(TestCommand $command): void
    {
    }

    public function doSomething(): void
    {
        $this->fire(new EmptyCommand());
    }

    /** @noinspection PhpUnusedParameterInspection */
    #[SagaEventListener]
    private function onEmptyEvent(EmptyEvent $event): void
    {
        $this->fail('test reason');
    }
}
