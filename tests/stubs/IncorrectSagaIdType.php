<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\stubs;

/**
 *
 */
final class IncorrectSagaIdType
{
    public function __construct(string $id, string $sagaClass)
    {
    }
}
