<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Tests\stubs\EmptyCommand;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class SagaWithInvalidListenerArg extends Saga
{
    public function start(object $command): void
    {
    }

    #[SagaEventListener]
    public function onSomeEvent(EmptyCommand $command): void
    {
    }
}
