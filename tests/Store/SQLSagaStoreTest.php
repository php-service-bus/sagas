<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Store;

use function Amp\call;
use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;

/**
 *
 */
final class SQLSagaStoreTest extends TestCase
{
    private DatabaseAdapter $adapter;

    private SQLSagaStore $store;

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new AmpPostgreSQLAdapter(
            new StorageConfiguration(
                (string) \getenv('TEST_POSTGRES_DSN')
            )
        );

        wait($this->adapter->execute(\file_get_contents(__DIR__ . '/../../src/Store/Sql/schema/extensions.sql')));
        wait($this->adapter->execute(\file_get_contents(__DIR__ . '/../../src/Store/Sql/schema/sagas_store.sql')));

        foreach (\file(__DIR__ . '/../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->store = new SQLSagaStore($this->adapter);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function obtain(): void
    {
        $store = $this->store;

        wait(
            call(
                static function () use ($store): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    /** @var CorrectSaga $loadedSaga */
                    $loadedSaga = yield $store->obtain($id);

                    static::assertNotNull($loadedSaga);
                    static::assertInstanceOf(CorrectSaga::class, $loadedSaga);
                    static::assertSame($id->id, $loadedSaga->id()->id);
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function remove(): void
    {
        $store = $this->store;

        wait(
            call(
                static function () use ($store): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);
                    yield $store->remove($id);

                    /** @var CorrectSaga|null $loadedSaga */
                    $loadedSaga = yield $store->obtain($id);

                    static::assertNull($loadedSaga);
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function removeUnExistsSaga(): void
    {
        $store = $this->store;

        wait(
            call(
                static function () use ($store): \Generator
                {
                    yield $store->remove(TestSagaId::new(CorrectSaga::class));
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function saveDuplicate(): void
    {
        $this->expectException(DuplicateSaga::class);

        $store = $this->store;

        wait(
            call(
                static function () use ($store): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);
                    yield $store->save($saga);
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function update(): void
    {
        $store = $this->store;

        wait(
            call(
                static function () use ($store): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $saga->changeValue('qwerty');

                    yield $store->update($saga);

                    /** @var CorrectSaga|null $loadedSaga */
                    $loadedSaga = yield $store->obtain($id);

                    static::assertSame($loadedSaga->value(), 'qwerty');
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function updateUnExistsSaga(): void
    {
        $store = $this->store;

        wait(
            call(
                static function () use ($store): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->update($saga);
                }
            )
        );
    }
}
