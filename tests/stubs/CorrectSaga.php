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

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use ServiceBus\Sagas\Configuration\Attributes\SagaInitialHandler;
use ServiceBus\Sagas\Saga;
use function ServiceBus\Common\uuid;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'requestId',
    expireDateModifier: '+1 year'
)]
final class CorrectSaga extends Saga
{
    /**
     * @var string|null
     */
    private $value;


    #[SagaInitialHandler(
        containingIdProperty: "id"
    )]
    public function start(CorrectSagaInitialCommand $command): void
    {
    }

    public function doSomething(): void
    {
        $this->fire(new EmptyCommand());
    }

    public function doSomethingElse(): void
    {
        $this->raise(new EventWithKey(uuid()));
    }

    public function closeWithSuccessStatus(): void
    {
        $this->complete();
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function changeValue(string $newValue): void
    {
        $this->value = $newValue;
    }

    private function onSomeFirstEvent(): void
    {
        $this->fail('test reason');
    }

    #[SagaEventListener(containingIdProperty: 'key')]
    private function onEventWithKey(EventWithKey $event): void
    {
        $this->raise(new SecondEventWithKey($event->key));
    }

    #[SagaEventListener(containingIdProperty: 'key')]
    private function onSecondEventWithKey(SecondEventWithKey $event): void
    {
    }

    #[SagaEventListener]
    private function onEmptyEvent(EmptyEvent $event): void
    {
    }
}
