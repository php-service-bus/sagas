<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Store;

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
    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @var SQLSagaStore
     */
    private $store;

    /**
     * @inheritdoc
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

        foreach(\file(__DIR__ . '/../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->store = new SQLSagaStore($this->adapter);
    }

    /**
     * @inheritdoc
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
     * @return void
     *
     * @throws \Throwable
     */
    public function obtain(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));

        /** @var CorrectSaga $loadedSaga */
        $loadedSaga = wait($this->store->obtain($id));

        static::assertNotNull($loadedSaga);
        static::assertInstanceOf(CorrectSaga::class, $loadedSaga);
        static::assertEquals($id, $loadedSaga->id());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function remove(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));
        wait($this->store->remove($id));

        /** @var CorrectSaga|null $loadedSaga */
        $loadedSaga = wait($this->store->obtain($id));

        static::assertNull($loadedSaga);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function removeUnExistsSaga(): void
    {
        wait($this->store->remove(TestSagaId::new(CorrectSaga::class)));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function saveDuplicate(): void
    {
        $this->expectException(DuplicateSaga::class);

        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));
        wait($this->store->save($saga));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function update(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));

        $saga->changeValue('qwerty');

        wait($this->store->update($saga));

        /** @var CorrectSaga|null $loadedSaga */
        $loadedSaga = wait($this->store->obtain($id));

        static::assertEquals($loadedSaga->value(), 'qwerty');
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function updateUnExistsSaga(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->update($saga));
    }
}
