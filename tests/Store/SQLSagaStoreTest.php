<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Store;

use Amp\Loop;
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

                yield $this->store->save($saga);

                /** @var CorrectSaga $loadedSaga */
                $loadedSaga = yield $this->store->obtain($id);

                self::assertNotNull($loadedSaga);
                self::assertInstanceOf(CorrectSaga::class, $loadedSaga);
                self::assertSame($id->id, $loadedSaga->id()->id);
            }
        );
    }

    /**
     * @test
     */
    public function remove(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);
                yield $this->store->remove($id);

                /** @var CorrectSaga|null $loadedSaga */
                $loadedSaga = yield $this->store->obtain($id);

                self::assertNull($loadedSaga);
            }
        );
    }

    /**
     * @test
     */
    public function removeUnExistsSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                yield $this->store->remove(TestSagaId::new(CorrectSaga::class));
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

                yield $this->store->save($saga);
                yield $this->store->save($saga);
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

                yield $this->store->save($saga);

                $saga->changeValue('qwerty');

                yield $this->store->update($saga);

                /** @var CorrectSaga|null $loadedSaga */
                $loadedSaga = yield $this->store->obtain($id);

                self::assertSame($loadedSaga->value(), 'qwerty');
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

                yield $this->store->update($saga);
            }
        );
    }
}
