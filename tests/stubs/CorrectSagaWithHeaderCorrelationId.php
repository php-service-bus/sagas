<?php /** @noinspection ALL */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaEventListener;
use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;
use function ServiceBus\Common\uuid;
use ServiceBus\Sagas\Saga;

#[SagaHeader(
    idClass: TestSagaId::class,
    containingIdProperty: 'saga-correlation-id',
    containingIdSource: 'headers',
    expireDateModifier: '+1 year'
)]
final class CorrectSagaWithHeaderCorrelationId extends Saga
{
    /**
     * @var string|null
     */
    private $value;

    public function start(object $command): void
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
        $this->makeCompleted();
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
        $this->makeFailed('test reason');
    }

    #[SagaEventListener(
        containingIdSource: 'headers',
        containingIdProperty: 'saga-correlation-id'
    )]
    private function onEventWithKey(EventWithKey $event): void
    {
        $this->raise(new SecondEventWithKey($event->key));
    }

    #[SagaEventListener]
    private function onSecondEventWithKey(SecondEventWithKey $event): void
    {
    }

    #[SagaEventListener]
    private function onEmptyEvent(EmptyEvent $event): void
    {
    }
}
