<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Association;

use ServiceBus\Sagas\SagaId;

final class SagaAssociation
{
    /**
     * @psalm-readonly
     *
     * @var SagaId
     */
    public $sagaId;

    /**
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $propertyName;

    /**
     * @psalm-readonly
     * @psalm-var non-empty-string|int
     *
     * @var string|int
     */
    public $propertyValue;

    /**
     * @psalm-param non-empty-string     $propertyName
     * @psalm-param non-empty-string|int $propertyValue
     */
    public function __construct(SagaId $sagaId, string $propertyName, int|string $propertyValue)
    {
        $this->sagaId        = $sagaId;
        $this->propertyName  = $propertyName;
        $this->propertyValue = $propertyValue;
    }
}
