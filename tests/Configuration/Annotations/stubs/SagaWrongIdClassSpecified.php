<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Sagas\Configuration\Annotations\SagaHeader;

/**
 * @SagaHeader(
 *     containingIdProperty="requestId",
 *     idClass="SomeIdClass"
 * )
 */
final class SagaWrongIdClassSpecified
{

}
