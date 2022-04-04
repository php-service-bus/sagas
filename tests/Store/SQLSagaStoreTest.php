<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\Store;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function Amp\Promise\wait;

/**
 *
 */
final class SQLSagaStoreTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @var SQLSagaStore
     */
    private $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new AmpPostgreSQLAdapter(
            new StorageConfiguration(
                (string) \getenv('TEST_POSTGRES_DSN')
            )
        );

        wait($this->adapter->execute(\file_get_contents(__DIR__ . '/../../src/Store/Sql/schema/extensions.sql')));

        $queries = \explode(
            ';',
            \file_get_contents(__DIR__ . '/../../src/Store/Sql/schema/sagas_store.sql')
        );

        foreach ($queries as $tableQuery)
        {
            if (!empty(trim($tableQuery)))
            {
                wait($this->adapter->execute($tableQuery));
            }
        }

        foreach (\file(__DIR__ . '/../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->store = new SQLSagaStore($this->adapter);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter);
    }

    /**
     * @test
     */
    public function obtain(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save(
                    saga: $saga,
                    publisher: static function ()
                    {
                    }
                );

                /** @var CorrectSaga $loadedSaga */
                $loadedSaga = yield $this->store->obtain($id);

                self::assertNotNull($loadedSaga);
                self::assertInstanceOf(CorrectSaga::class, $loadedSaga);
                self::assertSame($id->id, $loadedSaga->id()->id);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function saveDuplicate(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(DuplicateSaga::class);

                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save(
                    saga: $saga,
                    publisher: static function ()
                    {
                    }
                );

                yield $this->store->save(
                    saga: $saga,
                    publisher: static function ()
                    {
                    }
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function update(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save(
                    saga: $saga,
                    publisher: static function ()
                    {
                    }
                );

                $saga->changeValue('qwerty');

                yield $this->store->update(
                    saga: $saga,
                    publisher: static function ()
                    {
                    }
                );

                /** @var CorrectSaga|null $loadedSaga */
                $loadedSaga = yield $this->store->obtain($id);

                self::assertSame($loadedSaga->value(), 'qwerty');

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function updateUnExistsSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->update(
                    saga: $saga,
                    publisher: static function ()
                    {
                    }
                );

                Loop::stop();

                self::assertTrue(true);
            }
        );
    }
}
