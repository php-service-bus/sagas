<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Exceptions;

use ServiceBus\Sagas\SagaId;

final class IncorrectAssociation extends \RuntimeException
{
    public static function emptyPropertyName(SagaId $id): self
    {
        return new self(\sprintf('Associated property name can\'t be empty (SagaId: `%s`)', $id->toString()));
    }

    public static function emptyPropertyValue(string $propertyName, SagaId $id): self
    {
        return new self(
            \sprintf(
                'The value of the associated property `%s` can\'t be empty (SagaId: `%s`)',
                $propertyName,
                $id->toString()
            )
        );
    }

    public static function alreadyExists(string $propertyName, SagaId $id): self
    {
        return new self(
            \sprintf(
                'The association between the `%s` field and the `%s` saga already exists',
                $propertyName,
                $id->toString()
            )
        );
    }
}
