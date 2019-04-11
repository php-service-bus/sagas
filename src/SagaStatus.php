<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas;

use ServiceBus\Sagas\Exceptions\InvalidSagaStatus;

/**
 * SagaStatus of the saga.
 *
 * @internal
 */
final class SagaStatus
{
    private const STATUS_IN_PROGRESS = 'in_progress';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_FAILED = 'failed';

    private const STATUS_EXPIRED = 'expired';

    private const LIST           = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
    ];

    /**
     * SagaStatus ID.
     *
     * @var string
     */
    private $value;

    /**
     * @param string $value
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaStatus
     *
     * @return self
     *
     */
    public static function create(string $value): self
    {
        if(false === \in_array($value, self::LIST, true))
        {
            throw InvalidSagaStatus::create($value);
        }

        return new self($value);
    }

    /**
     * Create a new saga status.
     *
     * @return self
     */
    public static function created(): self
    {
        return new self(self::STATUS_IN_PROGRESS);
    }

    /**
     * Creating the status of a successfully completed saga.
     *
     * @return self
     */
    public static function completed(): self
    {
        return new self(self::STATUS_COMPLETED);
    }

    /**
     * Creating the status of an error-complete saga.
     *
     * @return self
     */
    public static function failed(): self
    {
        return new self(self::STATUS_FAILED);
    }

    /**
     * Creation of the status of the expired life of the saga.
     *
     * @return self
     */
    public static function expired(): self
    {
        return new self(self::STATUS_EXPIRED);
    }

    /**
     * Is processing status.
     *
     * @return bool
     */
    public function inProgress(): bool
    {
        return self::STATUS_IN_PROGRESS === $this->value;
    }

    /**
     * @param SagaStatus $status
     *
     * @return bool
     */
    public function equals(SagaStatus $status): bool
    {
        return $this->value === $status->value;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * @deprecated
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }
}
