<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Common\Messages\Command;
use function ServiceBus\Common\uuid;
use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;
use ServiceBus\Sagas\Configuration\Annotations\SagaEventListener;
use ServiceBus\Sagas\Saga;

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class CorrectSaga extends Saga
{
    /**
     * @var string|null
     */
    private $value;

    /**
     * @inheritdoc
     */
    public function start(Command $command): void
    {

    }

    /**
     * @return void
     *
     * @throws \Throwable
     */
    public function doSomething(): void
    {
        $this->fire(new EmptyCommand());
    }

    /**
     * @return void
     *
     * @throws \Throwable
     */
    public function doSomethingElse(): void
    {
        $this->raise(new EventWithKey(uuid()));
    }

    /**
     * @return void
     *
     * @throws \Throwable
     */
    public function closeWithSuccessStatus(): void
    {
        $this->makeCompleted();
    }

    /**
     * @return string|null
     */
    public function value(): ?string
    {
        return $this->value;
    }

    /**
     * @param string $newValue
     *
     * @return void
     */
    public function changeValue(string $newValue): void
    {
        $this->value = $newValue;
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @return void
     *
     * @throws \Throwable
     */
    private function onSomeFirstEvent(): void
    {
        $this->makeFailed('test reason');
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener(
     *     containingIdProperty="key"
     * )
     *
     * @param EventWithKey $event
     *
     * @return void
     *
     * @throws \Throwable
     */
    private function onEventWithKey(EventWithKey $event): void
    {
        $this->raise(new SecondEventWithKey($event->key));
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener(
     *     containingIdProperty="key"
     * )
     *
     * @param SecondEventWithKey $event
     *
     * @return void
     */
    private function onSecondEventWithKey(SecondEventWithKey $event): void
    {

    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener()
     *
     * @param EmptyEvent $event
     *
     * @return void
     */
    private function onEmptyEvent(EmptyEvent $event): void
    {

    }
}
