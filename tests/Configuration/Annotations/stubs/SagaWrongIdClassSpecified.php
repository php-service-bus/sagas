<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations\stubs;

use ServiceBus\Sagas\Configuration\Attributes\SagaHeader;

#[SagaHeader(
    idClass: 'SomeIdClass',
    containingIdProperty: 'requestId'
)]
final class SagaWrongIdClassSpecified
{
}
