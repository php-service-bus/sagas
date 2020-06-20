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

use ServiceBus\Sagas\SagaStatus;
use function Amp\call;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\writeReflectionPropertyValue;
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

    /** @var DatabaseAdapter */
    private $adapter;

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function obtain(SagaId $id): Promise
    {
        return call(
            function () use ($id): \Generator
            {
                try
                {
                    $criteria = [
                        equalsCriteria('id', $id->toString()),
                        equalsCriteria('identifier_class', \get_class($id)),
                    ];

                    /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                    $resultSet = yield find($this->adapter, self::SAGA_STORE_TABLE, $criteria);

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

                    if ($result !== null)
                    {
                        $payload = $result['payload'];

                        if ($this->adapter instanceof BinaryDataDecoder)
                        {
                            $payload = $this->adapter->unescapeBinary($payload);
                        }

                        /** @var Saga $saga */
                        $saga = unserializeSaga($payload);

                        /**
                         * Due to the fact that the data is stored in serialized form, when deserializing an object,
                         * we need to update the main parameters of the saga
                         *
                         * @todo: fix me
                         */
                        writeReflectionPropertyValue($saga, 'status', SagaStatus::create($result['state_id']));
                        writeReflectionPropertyValue($saga, 'expireDate', datetimeInstantiator($result['expiration_date']));

                        if ($result['closed_at'] !== null)
                        {
                            writeReflectionPropertyValue(
                                $saga,
                                'closedAt',
                                datetimeInstantiator($result['closed_at'])
                            );
                        }

                        return $saga;
                    }
                }
                catch (\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function save(Saga $saga): Promise
    {
        return call(
            function () use ($saga): \Generator
            {
                try
                {
                    $id = $saga->id();

                    /** @var \ServiceBus\Sagas\SagaStatus $status */
                    $status   = readReflectionPropertyValue($saga, 'status');
                    $closedAt = $saga->closedAt();

                    $closedDatetime = $closedAt !== null ? $closedAt->format('Y-m-d H:i:s.u') : null;

                    /** @var \Latitude\QueryBuilder\Query\InsertQuery $insertQuery */
                    $insertQuery = insertQuery(self::SAGA_STORE_TABLE, [
                        'id'               => $id->toString(),
                        'identifier_class' => \get_class($id),
                        'saga_class'       => \get_class($saga),
                        'payload'          => serializeSaga($saga),
                        'state_id'         => $status->toString(),
                        'created_at'       => $saga->createdAt()->format('Y-m-d H:i:s.u'),
                        'expiration_date'  => $saga->expireDate()->format('Y-m-d H:i:s.u'),
                        'closed_at'        => $closedDatetime,
                    ]);

                    $compiledQuery = $insertQuery->compile();

                    /** @psalm-suppress MixedTypeCoercion */
                    yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());
                }
                catch (UniqueConstraintViolationCheckFailed $exception)
                {
                    throw new DuplicateSaga('Duplicate saga id', (int) $exception->getCode(), $exception);
                }
                catch (\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function update(Saga $saga): Promise
    {
        return call(
            function () use ($saga): \Generator
            {
                try
                {
                    $id = $saga->id();

                    /** @var \ServiceBus\Sagas\SagaStatus $status */
                    $status   = readReflectionPropertyValue($saga, 'status');
                    $closedAt = $saga->closedAt();

                    $closedDatetime = $closedAt !== null ? $closedAt->format('Y-m-d H:i:s.u') : null;

                    $updateQuery = updateQuery(self::SAGA_STORE_TABLE, [
                        'payload'   => serializeSaga($saga),
                        'state_id'  => $status->toString(),
                        'closed_at' => $closedDatetime
                    ])
                        ->where(equalsCriteria('id', $id->toString()))
                        ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                    /** @var \Latitude\QueryBuilder\Query $compiledQuery */
                    $compiledQuery = $updateQuery->compile();

                    /** @psalm-suppress MixedTypeCoercion */
                    yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());
                }
                catch (\Throwable $throwable)
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
        return call(
            function () use ($id): \Generator
            {
                try
                {
                    $criteria = [
                        equalsCriteria('id', $id->toString()),
                        equalsCriteria('identifier_class', \get_class($id)),
                    ];

                    yield remove($this->adapter, self::SAGA_STORE_TABLE, $criteria);
                }
                catch (\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            },
            $id
        );
    }
}
