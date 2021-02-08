<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Exceptions;

use ServiceBus\Sagas\SagaId;

/**
 *
 */
final class ReopenFailed extends \RuntimeException
{
    public static function stillALive(SagaId $id): self
    {
        return new self(\sprintf('Unable to open unfinished saga `%s`', $id->toString()));
    }

    public static function incorrectExpirationDate(SagaId $id): self
    {
        return new self(\sprintf('Unable to reopen the saga `%s`: expiration date is incorrect', $id->toString()));
    }
}
