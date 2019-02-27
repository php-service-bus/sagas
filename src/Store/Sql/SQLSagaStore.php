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
use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\selectQuery;
use function ServiceBus\Storage\Sql\updateQuery;
use Amp\Promise;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;

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

    /**
     * @param DatabaseAdapter $adapter
     */
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
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(SagaId $id): \Generator
            {
                /** @var array{payload:string}|null $result */
                $result = yield from $this->doLoadEntry($id);

                if (null === $result)
                {
                    return null;
                }

                $result['payload'] = $this->adapter->unescapeBinary($result['payload']);

                return unserializeSaga(
                    $this->adapter->unescapeBinary($result['payload'])
                );
            },
            $id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function save(Saga $saga): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Saga $saga): \Generator
            {
                $id = $saga->id();

                /** @var \ServiceBus\Sagas\SagaStatus $status */
                $status = readReflectionPropertyValue($saga, 'status');

                /** @var \Latitude\QueryBuilder\Query\InsertQuery $insertQuery */
                $insertQuery = insertQuery(self::SAGA_STORE_TABLE, [
                    'id'               => (string) $id,
                    'identifier_class' => \get_class($id),
                    'saga_class'       => \get_class($saga),
                    'payload'          => serializeSaga($saga),
                    'state_id'         => (string) $status,
                    'created_at'       => datetimeToString($saga->createdAt()),
                    'expiration_date'  => datetimeToString($saga->expireDate()),
                    'closed_at'        => datetimeToString($saga->closedAt()),
                ]);

                $compiledQuery = $insertQuery->compile();

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield from $this->doExecuteQuery($compiledQuery->sql(), $compiledQuery->params());

                unset($resultSet, $id, $status, $insertQuery, $compiledQuery);
            },
            $saga
        );
    }

    /**
     * {@inheritdoc}
     */
    public function update(Saga $saga): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Saga $saga): \Generator
            {
                /** @var \ServiceBus\Sagas\SagaStatus $status */
                $status = readReflectionPropertyValue($saga, 'status');

                $updateQuery = updateQuery(self::SAGA_STORE_TABLE, [
                    'payload'   => serializeSaga($saga),
                    'state_id'  => (string) $status,
                    'closed_at' => datetimeToString($saga->closedAt()),
                ])
                    ->where(equalsCriteria('id', $saga->id()))
                    ->andWhere(equalsCriteria('identifier_class', \get_class($saga->id())));

                $compiledQuery = $updateQuery->compile();

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield from $this->doExecuteQuery($compiledQuery->sql(), $compiledQuery->params());

                unset($status, $updateQuery, $compiledQuery, $resultSet);
            },
            $saga
        );
    }

    /**
     * {@inheritdoc}
     */
    public function remove(SagaId $id): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(SagaId $id): \Generator
            {
                $deleteQuery = deleteQuery(self::SAGA_STORE_TABLE)
                    ->where(equalsCriteria('id', $id))
                    ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                $compiledQuery = $deleteQuery->compile();

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield from $this->doExecuteQuery($compiledQuery->sql(), $compiledQuery->params());

                unset($deleteQuery, $compiledQuery, $resultSet);
            },
            $id
        );
    }

    /**
     * Load saga entry.
     *
     * @param SagaId $id
     *
     * @throws SagasStoreInteractionFailed
     *
     * @return \Generator
     *
     */
    private function doLoadEntry(SagaId $id): \Generator
    {
        try
        {
            /** @psalm-suppress ImplicitToStringCast */
            $selectQuery = selectQuery(self::SAGA_STORE_TABLE, 'payload')
                ->where(equalsCriteria('id', $id))
                ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

            $compiledQuery = $selectQuery->compile();

            /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
            $resultSet = yield from $this->doExecuteQuery($compiledQuery->sql(), $compiledQuery->params());

            /** @var array|null $result */
            $result = yield fetchOne($resultSet);

            unset($selectQuery, $compiledQuery, $resultSet);

            return $result;
        }
        catch (\Throwable $throwable)
        {
            throw SagasStoreInteractionFailed::fromThrowable($throwable);
        }
    }

    /**
     * @param string $query
     * @param array  $parameters
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     *
     * @return \Generator
     *
     */
    private function doExecuteQuery(string $query, array $parameters): \Generator
    {
        try
        {
            /**
             * @psalm-suppress TooManyTemplateParams Wrong Promise template
             * @psalm-suppress MixedTypeCoercion Invalid params() docblock
             *
             * @var \ServiceBus\Storage\Common\ResultSet $resultSet
             */
            $resultSet = yield $this->adapter->execute($query, $parameters);

            return $resultSet;
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
}
