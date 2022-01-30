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

use ServiceBus\Common\Context\IncomingMessageMetadata;
use function ServiceBus\Common\uuid;

/**
 *
 */
final class TestIncomingMessageMetadata implements IncomingMessageMetadata
{
    /**
     * @var string
     */
    private $messageId;

    /**
     * @psalm-var array<string, string|int|float|bool|null>
     * @var array
     */
    private $variables;

    public function traceId(): string
    {
        return uuid();
    }

    public function messageId(): string
    {
        return $this->messageId;
    }

    public function variables(): array
    {
        return $this->variables;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->variables);
    }

    public function get(string $key, float|bool|int|string|null $default = null): string|int|float|bool|null
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * @psalm-param array<string, string|int|float|bool|null> $variables
     */
    public function __construct(string $messageId, array $variables)
    {
        $this->messageId = $messageId;
        $this->variables = $variables;
    }
}
