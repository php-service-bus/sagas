<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use ServiceBus\Common\Messages\Command;
use function ServiceBus\Common\uuid;
use ServiceBus\Sagas\Saga;

/**
 *
 */
final class CorrectSaga extends Saga
{
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
     * @param SecondEventWithKey $event
     *
     * @return void
     */
    private function onSecondEventWithKey(SecondEventWithKey $event): void
    {

    }
}
