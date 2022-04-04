<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas;

use ServiceBus\Sagas\Configuration\Metadata\SagaMetadata;

/**
 * @internal
 */
final class SagaMetadataStore
{
    /**
     * @var null|self
     */
    private static $instance;

    /**
     * @psalm-var array<class-string<\ServiceBus\Sagas\Saga>, \ServiceBus\Sagas\Configuration\Metadata\SagaMetadata>
     *
     * @var SagaMetadata[]
     */
    private $storage = [];

    public static function instance(): self
    {
        if (self::$instance === null)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function reset(): void
    {
        self::$instance = null;
    }

    public function add(SagaMetadata $metadata): void
    {
        $this->storage[$metadata->sagaClass] = $metadata;
    }

    /**
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     */
    public function get(string $sagaClass): ?SagaMetadata
    {
        return $this->storage[$sagaClass] ?? null;
    }

    /**
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
