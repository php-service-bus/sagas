<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Store\Sql;

use ServiceBus\Sagas\SagaStatus;
use ServiceBus\Storage\Common\QueryExecutor;
use Amp\Promise;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Storage\Common\BinaryDataDecoder;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use function Amp\call;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\writeReflectionPropertyValue;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\updateQuery;

/**
 * Sql sagas storage.
 */
final class SQLSagaStore implements SagasStore
{
    private const SAGA_STORE_TABLE = 'sagas_store';

    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

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
                    $resultSet = yield find(
                        queryExecutor: $this->adapter,
                        tableName: self::SAGA_STORE_TABLE,
                        criteria: $criteria
                    );

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

                        /** @psalm-var Saga $saga */
                        $saga = unserializeSaga($payload);

                        /**
                         * Due to the fact that the data is stored in serialized form, when deserializing an object,
                         * we need to update the main parameters of the saga
                         *
                         * @todo: fix me
                         */
                        writeReflectionPropertyValue(
                            object: $saga,
                            propertyName: 'status',
                            value: SagaStatus::from($result['state_id'])
                        );

                        writeReflectionPropertyValue(
                            object: $saga,
                            propertyName: 'expireDate',
                            value: datetimeInstantiator($result['expiration_date'])
                        );

                        if ($result['closed_at'] !== null)
                        {
                            writeReflectionPropertyValue(
                                object: $saga,
                                propertyName: 'closedAt',
                                value: datetimeInstantiator($result['closed_at'])
                            );
                        }

                        return $saga;
                    }

                    return null;
                }
                catch (\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            }
        );
    }

    public function save(Saga $saga, callable $publisher): Promise
    {
        return call(
            function () use ($saga, $publisher): \Generator
            {
                try
                {
                    yield $this->adapter->transactional(
                        static function (QueryExecutor $executor) use ($saga, $publisher): \Generator
                        {
                            $id = $saga->id();

                            /** @var \ServiceBus\Sagas\SagaStatus $status */
                            $status   = readReflectionPropertyValue($saga, 'status');
                            $closedAt = $saga->closedAt();

                            $insertQuery = insertQuery(self::SAGA_STORE_TABLE, [
                                'id'               => $id->toString(),
                                'identifier_class' => \get_class($id),
                                'saga_class'       => \get_class($saga),
                                'payload'          => serializeSaga($saga),
                                'state_id'         => $status->value,
                                'created_at'       => $saga->createdAt()->format('Y-m-d H:i:s.u'),
                                'expiration_date'  => $saga->expireDate()->format('Y-m-d H:i:s.u'),
                                'closed_at'        => $closedAt?->format('Y-m-d H:i:s.u'),
                            ]);

                            $compiledQuery = $insertQuery->compile();

                            /** @psalm-suppress MixedArgumentTypeCoercion */
                            yield $executor->execute(
                                queryString: $compiledQuery->sql(),
                                parameters: $compiledQuery->params()
                            );

                            yield call($publisher);
                        }
                    );
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

    public function update(Saga $saga, callable $publisher): Promise
    {
        return call(
            function () use ($saga, $publisher): \Generator
            {
                try
                {
                    yield $this->adapter->transactional(
                        static function (QueryExecutor $executor) use ($saga, $publisher): \Generator
                        {
                            $id = $saga->id();

                            /** @var \ServiceBus\Sagas\SagaStatus $status */
                            $status   = readReflectionPropertyValue($saga, 'status');
                            $closedAt = $saga->closedAt();

                            $closedDatetime = $closedAt?->format('Y-m-d H:i:s.u');

                            $updateQuery = updateQuery(self::SAGA_STORE_TABLE, [
                                'payload'         => serializeSaga($saga),
                                'state_id'        => $status->value,
                                'closed_at'       => $closedDatetime,
                                'expiration_date' => $saga->expireDate()->format('Y-m-d H:i:s.u')
                            ])
                                ->where(equalsCriteria('id', $id->toString()))
                                ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                            $compiledQuery = $updateQuery->compile();

                            /** @psalm-suppress MixedArgumentTypeCoercion */
                            yield $executor->execute(
                                queryString: $compiledQuery->sql(),
                                parameters: $compiledQuery->params()
                            );

                            yield call($publisher);
                        }
                    );
                }
                catch (\Throwable $throwable)
                {
                    throw SagasStoreInteractionFailed::fromThrowable($throwable);
                }
            }
        );
    }
}
