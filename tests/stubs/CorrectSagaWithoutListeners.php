<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Configuration\Attributes\SagaInitialHandler;
use ServiceBus\Sagas\Saga;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class CorrectSagaWithoutListeners extends Saga
{
    #[SagaInitialHandler]
    public function start(CorrectSagaWithoutListenersInitialCommand $command): void
    {
    }
}
