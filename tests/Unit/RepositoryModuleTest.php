<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Modules\ModelModule;
use Readfly\Laravel\Modules\RepositoryModule;

class TestUserModel extends ModelModule
{
    protected $table = self::TABLE_NAME;

    protected $connection = self::DB_CONNECTION;

    public const TABLE_NAME = 'users';

    public const DB_CONNECTION = 'default';

    public const AUTO_TIMESTAMP = true;
}

class RepositoryModuleTest extends TestCase
{
    protected RepositoryModule $repository;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->integer('age')->default(0);
            $table->string('email')->nullable();
            $table->text('meta')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $this->repository = new RepositoryModule(new TestUserModel());
    }

    public function test_insert_and_find(): void
    {
        $id = $this->repository->insertGetId(['name' => '张三', 'age' => 18, 'meta' => ['tags' => ['a']]]);

        $this->assertIsNumeric($id);
        $this->assertSame('张三', $this->repository->find(['id' => $id], 'name'));

        $row = $this->repository->row(['id' => $id]);
        $this->assertSame(18, $row['age']);
        $this->assertSame(['tags' => ['a']], json_decode($row['meta'], true));
        $this->assertNotEmpty($row['created_at']);
    }

    public function test_conditions_dsl(): void
    {
        $this->repository->insertAll([
            ['name' => '张三', 'age' => 18],
            ['name' => '李四', 'age' => 25],
            ['name' => '王五', 'age' => 30],
        ]);

        $this->assertSame(2, $this->repository->count(['age' => ['>', 18]]));
        $this->assertSame(2, $this->repository->count(['age' => ['in', [18, 30]]]));
        $this->assertSame(1, $this->repository->count(['age' => ['between', [24, 26]]]));
        $this->assertSame(3, $this->repository->count(['email' => ['null']]));

        $names = array_column($this->repository->get(['age' => ['>=', 25]], ['name'], [], ['age' => 'desc']), 'name');
        $this->assertSame(['王五', '李四'], $names);
    }

    public function test_like_ignores_spaces_and_is_bound(): void
    {
        $this->repository->insertAll([
            ['name' => '张 三', 'age' => 10],
            ['name' => '张三丰', 'age' => 20],
            ['name' => '李四', 'age' => 30],
        ]);

        $this->assertSame(2, $this->repository->count(['name' => ['like', '张 三']]));
        $this->assertSame(2, $this->repository->count(['name|email' => ['like', '张 三']]));
    }

    public function test_lists_pagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->repository->insertGetId(['name' => '用户'.$i, 'age' => $i]);
        }

        $result = $this->repository->lists([], ['name'], [], ['id' => 'desc'], '', 1, 10);

        $this->assertSame(15, $result['total_count']);
        $this->assertSame(15, $result['matched_count']);
        $this->assertCount(10, $result['lists']);
        $this->assertSame(2, $result['total_pages']);
    }

    public function test_update_delete_and_exists(): void
    {
        $id = $this->repository->insertGetId(['name' => '张三', 'age' => 18]);

        $this->assertTrue($this->repository->exists(['id' => $id]));
        $this->assertSame(1, $this->repository->update(['id' => $id], ['age' => 20]));
        $this->assertSame(20, $this->repository->find(['id' => $id], 'age'));
        $this->assertFalse($this->repository->doesntExists(['id' => $id]));

        $this->repository->delete(['id' => $id]);
        $this->assertFalse($this->repository->exists(['id' => $id]));
    }

    public function test_invalid_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->count(["name' OR '1'='1" => 'x']);
    }

    public function test_unique_code(): void
    {
        $code = $this->repository->uniqueCode('email', 'int:6');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_update_or_insert(): void
    {
        $this->repository->updateOrInsert(['name' => '张三'], ['age' => 18]);
        $this->repository->updateOrInsert(['name' => '张三'], ['age' => 20]);

        $this->assertSame(1, $this->repository->count(['name' => '张三']));
        $this->assertSame(20, $this->repository->find(['name' => '张三'], 'age'));
    }

    public function test_insert_without_returning_id(): void
    {
        $this->assertTrue($this->repository->insert(['name' => '直接插入', 'age' => 9]));
        $this->assertTrue($this->repository->exists(['name' => '直接插入']));
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        $id = $this->repository->insertGetId(['name' => '待删除', 'age' => 1]);

        $this->assertSame(1, $this->repository->softDelete(['id' => $id]));
        $this->assertNotNull($this->repository->find(['id' => $id], 'deleted_at'));
    }
}
