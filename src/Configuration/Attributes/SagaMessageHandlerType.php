<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Configuration\Attributes;

enum SagaMessageHandlerType: int
{
    case INITIAL_COMMAND_HANDLER = 1;
    case EVENT_LISTENER = 2;
}
