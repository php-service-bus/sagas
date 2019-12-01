<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Store\Sql;

use function Amp\call;
use function ServiceBus\Common\datetimeToString;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\remove;
use function ServiceBus\Storage\Sql\updateQuery;
use Amp\Promise;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Storage\Common\BinaryDataDecoder;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;

/**
 * Sql sagas storage.
 */
final class SQLSagaStore implements SagasStore
{
    private const SAGA_STORE_TABLE = 'sagas_store';

    private DatabaseAdapter $adapter;

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @psalm-suppress MixedTypeCoercion
     *
     * {@inheritdoc}
     */
    public function obtain(SagaId $id): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(SagaId $id) use ($adapter): \Generator
            {
                try
                {
                    $criteria = [
                        equalsCriteria('id', $id->toString()),
                        equalsCriteria('identifier_class', \get_class($id)),
                    ];

                    /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                    $resultSet = yield find($adapter, self::SAGA_STORE_TABLE, $criteria);

                    /**
                     * @psalm-var array{
                     *   id: string,
                     *   identifier_class: string,
                     *   saga_class: string,
                     *   payload: string,
                     *   state_id: string,
                     *   created_at: string,
                     *   expiration_date: string,
                     *   closed_at: string|null
                     * }|null $result
                     *
                     * @var array|null $result
                     */
                    $result = yield fetchOne($resultSet);

                    if(null === $result)
                    {
                        return null;
                    }

                    $payload = $result['payload'];

                    if($adapter instanceof BinaryDataDecoder)
                    {
                        $payload = $adapter->unescapeBinary($payload);
                    }

                    return unserializeSaga($payload);
                }
                catch(\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            },
            $id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function save(Saga $saga): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(Saga $saga) use ($adapter): \Generator
            {
                try
                {
                    $id = $saga->id();

                    /** @var \ServiceBus\Sagas\SagaStatus $status */
                    $status = readReflectionPropertyValue($saga, 'status');

                    /** @var \Latitude\QueryBuilder\Query\InsertQuery $insertQuery */
                    $insertQuery = insertQuery(self::SAGA_STORE_TABLE, [
                        'id'               => $id->toString(),
                        'identifier_class' => \get_class($id),
                        'saga_class'       => \get_class($saga),
                        'payload'          => serializeSaga($saga),
                        'state_id'         => $status->toString(),
                        'created_at'       => datetimeToString($saga->createdAt()),
                        'expiration_date'  => datetimeToString($saga->expireDate()),
                        'closed_at'        => datetimeToString($saga->closedAt()),
                    ]);

                    $compiledQuery = $insertQuery->compile();

                    /**
                     * @psalm-suppress TooManyTemplateParams
                     * @psalm-suppress MixedTypeCoercion
                     */
                    yield $adapter->execute($compiledQuery->sql(), $compiledQuery->params());
                }
                catch(UniqueConstraintViolationCheckFailed $exception)
                {
                    throw new DuplicateSaga('Duplicate saga id', (int) $exception->getCode(), $exception);
                }
                catch(\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            },
            $saga
        );
    }

    /**
     * {@inheritdoc}
     */
    public function update(Saga $saga): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(Saga $saga) use ($adapter): \Generator
            {
                try
                {
                    $id = $saga->id();

                    /** @var \ServiceBus\Sagas\SagaStatus $status */
                    $status = readReflectionPropertyValue($saga, 'status');

                    $updateQuery = updateQuery(self::SAGA_STORE_TABLE, [
                        'payload'   => serializeSaga($saga),
                        'state_id'  => $status->toString(),
                        'closed_at' => datetimeToString($saga->closedAt()),
                    ])
                        ->where(equalsCriteria('id', $id->toString()))
                        ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                    /** @var \Latitude\QueryBuilder\Query $compiledQuery */
                    $compiledQuery = $updateQuery->compile();

                    /**
                     * @psalm-suppress MixedTypeCoercion
                     * @psalm-suppress TooManyTemplateParams
                     */
                    yield $adapter->execute($compiledQuery->sql(), $compiledQuery->params());
                }
                catch(\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            },
            $saga
        );
    }

    /**
     * {@inheritdoc}
     */
    public function remove(SagaId $id): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(SagaId $id) use ($adapter): \Generator
            {
                try
                {
                    $criteria = [
                        equalsCriteria('id', $id->toString()),
                        equalsCriteria('identifier_class', \get_class($id)),
                    ];

                    yield remove($adapter, self::SAGA_STORE_TABLE, $criteria);
                }
                catch(\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            },
            $id
        );
    }
}
