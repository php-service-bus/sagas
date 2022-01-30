<?php

/** @noinspection PhpUnusedPrivateMethodInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Tests\stubs\EventWithKey;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class SagaWithToManyArguments extends Saga
{
    public function start(object $command): void
    {
    }

    #[SagaEventListener]
    private function onSomeSagaEvent(EventWithKey $event, ServiceBusContext $context): void
    {
    }
}
