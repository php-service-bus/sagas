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

use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Sagas\Configuration\Annotations\SagaEventListener;
use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Tests\stubs\EventWithKey;

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class SagaWithToManyArguments extends Saga
{
    /**
     * {@inheritdoc}
     */
    public function start(object $command): void
    {
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener()
     */
    private function onSomeSagaEvent(EventWithKey $event, ServiceBusContext $context): void
    {
    }
}
