<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use function ServiceBus\Common\uuid;
use ServiceBus\Sagas\Configuration\Annotations\SagaEventListener;
use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;
use ServiceBus\Sagas\Saga;

/**
 * @SagaHeader(
 *     idClass="ServiceBus\Sagas\Tests\stubs\TestSagaId",
 *     containingIdSource="headers",
 *     containingIdProperty="saga-correlation-id",
 *     expireDateModifier="+1 year"
 * )
 */
final class CorrectSagaWithHeaderCorrelationId extends Saga
{
    /**
     * @var string|null
     */
    private $value;

    /**
     * {@inheritdoc}
     */
    public function start(object $command): void
    {
    }

    /**
     * @throws \Throwable
     *
     * @return void
     *
     */
    public function doSomething(): void
    {
        $this->fire(new EmptyCommand());
    }

    /**
     * @throws \Throwable
     *
     * @return void
     *
     */
    public function doSomethingElse(): void
    {
        $this->raise(new EventWithKey(uuid()));
    }

    /**
     * @throws \Throwable
     *
     * @return void
     *
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
     * @throws \Throwable
     *
     * @return void
     *
     */
    private function onSomeFirstEvent(): void
    {
        $this->makeFailed('test reason');
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @param EventWithKey $event
     *
     * @throws \Throwable
     *
     * @return void
     *
     */
    private function onEventWithKey(EventWithKey $event): void
    {
        $this->raise(new SecondEventWithKey($event->key));
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
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
