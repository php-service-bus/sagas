<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Sagas\Configuration\Annotations\SagaEventListener;
use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Tests\stubs\EmptyCommand;
use ServiceBus\Sagas\Tests\stubs\EmptyEvent;

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class SagaWithMultipleListenerArgs extends Saga
{
    /**
     * {@inheritdoc}
     */
    public function start(object $command): void
    {
    }

    /**
     * @SagaEventListener()
     */
    public function onEmptyEvent(EmptyEvent $firstEmptyEvent, EmptyCommand $command): void
    {
    }
}
