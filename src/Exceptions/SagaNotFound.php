<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Exceptions;

use ServiceBus\Sagas\SagaId;

final class SagaNotFound extends \RuntimeException
{
    public function __construct(SagaId $id)
    {
        parent::__construct(
            \sprintf('Saga (`%s`) with id `%s` was not found', $id->sagaClass, $id->id)
        );
    }
}
