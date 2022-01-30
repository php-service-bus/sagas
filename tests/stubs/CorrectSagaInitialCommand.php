<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\stubs;

final class CorrectSagaInitialCommand
{
    /**
     * @var string
     */
    public $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }
}
