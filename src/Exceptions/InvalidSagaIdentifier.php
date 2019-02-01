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
 * The class of the saga in the identifier differs from the saga to which it was transmitted
 */
final class InvalidSagaIdentifier extends \RuntimeException
{

}
