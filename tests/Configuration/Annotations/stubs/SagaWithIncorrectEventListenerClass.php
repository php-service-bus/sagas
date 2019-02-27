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

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class SagaWithIncorrectEventListenerClass extends Saga
{
    /**
     * {@inheritdoc}
     */
    public function start(object $command): void
    {
    }

    /**
     * @noinspection PhpUndefinedClassInspection
     *
     * @SagaEventListener()
     *
     * @param IncorrectSagaEvent $event
     *
     * @return void
     */
    public function onIncorrectSagaEvent(IncorrectSagaEvent $event): void
    {
    }
}
