<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Configuration\Attributes\SagaInitialHandler;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Tests\stubs\EmptyCommand;
use ServiceBus\Sagas\Tests\stubs\EventWithKey;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class SagaWithIncorrectListenerName extends Saga
{
    #[SagaInitialHandler]
    public function start(EmptyCommand $command): void
    {
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    #[SagaEventListener]
    private function wrongEventListenerName(EventWithKey $event): void
    {
    }
}
