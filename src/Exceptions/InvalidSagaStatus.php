<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Exceptions;

/**
 * Incorrect saga status indicated
 */
class InvalidSagaStatus extends \InvalidArgumentException
{
    /**
     * @param string $status
     */
    public function __construct(string $status)
    {
        parent::__construct(
            \sprintf('Incorrect saga status specified: %s', $status)
        );
    }
}
