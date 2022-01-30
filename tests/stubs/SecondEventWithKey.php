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
 * @psalm-immutable
 */
final class SecondEventWithKey
{
    /**
     * @psalm-readonly
     *
     * @var string
     */
    public $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }
}
