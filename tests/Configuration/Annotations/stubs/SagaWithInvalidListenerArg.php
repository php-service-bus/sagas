<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;
use ServiceBus\Sagas\Configuration\Annotations\SagaEventListener;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Tests\stubs\EmptyCommand;

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class SagaWithInvalidListenerArg extends Saga
{
    /**
     * @inheritdoc
     */
    public function start(object $command): void
    {

    }

    /**
     * @noinspection PhpUndefinedClassInspection
     *
     * @SagaEventListener()
     *
     * @param EmptyCommand $command
     *
     * @return void
     */
    public function onSomeEvent(EmptyCommand $command): void
    {

    }
}
