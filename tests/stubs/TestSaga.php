<?php /** @noinspection PhpUnusedPrivateMethodInspection */

/**
 * Saga pattern implementation module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Saga;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class TestSaga extends Saga
{
    public function start(object $command): void
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
        $this->makeFailed('test reason');
    }
}
