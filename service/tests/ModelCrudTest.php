<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Db;

/**
 * 全模型通用 CRUD 集成测试
 *
 * 覆盖 service/app/model 下全部 110 个业务模型（BaseModel 无表跳过）：
 *  - 建表结构驱动生成属性（enum 取首个枚举值、varchar 按长度截断、JSON 序列化等）
 *  - 批量赋值（fillable）+ 受保护列（money/score/level 经 forceFill 写入）
 *  - Snowflake 主键自动生成、find 回读、非键列更新持久化
 *  - 软删除模型（SoftDeletes）验证 delete/withTrashed/restore/forceDelete，
 *    普通模型验证 delete 后不可见
 */
class ModelCrudTest extends IntegrationTestCase
{
    public static function modelProvider(): array
    {
        $models = [];
        foreach (glob(dirname(__DIR__) . '/app/model/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'BaseModel') {
                continue;
            }
            $models[$name] = ['app\\model\\' . $name];
        }
        return $models;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modelProvider')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function crud_roundtrip(string $class): void
    {
        $this->requireDb();

        $table = (new $class())->getTable();
        $cols = Db::connection()->select('SHOW COLUMNS FROM `' . $table . '`');
        $attrs = $this->buildAttrs($cols);
        $guarded = $this->guardedAttrs($cols);

        // 批量赋值 + 受保护列直写（forceFill 绕过 $guarded）
        $model = new $class();
        $model->fill($attrs);
        $model->forceFill($guarded);
        $model->save();
        $id = $model->getKey();

        $this->assertNotNull($id, "{$class} create 后未生成主键");
        $found = $class::find($id);
        $this->assertNotNull($found, "{$class} find({$id}) 失败");

        // 非键列更新持久化
        $updateCol = $this->pickUpdatableCol($cols);
        if ($updateCol) {
            $newValue = is_numeric($model->{$updateCol}) ? 2 : 'updated-value';
            $model->{$updateCol} = $newValue;
            $model->save();
            $fresh = $class::find($id);
            $this->assertSame((string) $newValue, (string) $fresh->{$updateCol}, "{$class} {$updateCol} 更新未持久化");
        }

        // 软删除 vs 硬删除
        $uses = class_uses_recursive($class);
        if (isset($uses[SoftDeletes::class])) {
            $found->delete();
            $trashed = $class::withTrashed()->find($id);
            $this->assertNotNull($trashed, "{$class} 软删除失败");
            $this->assertNotNull($trashed->deleted_at);
            $this->assertNull($class::find($id));
            $trashed->restore();
            $this->assertNotNull($class::find($id), "{$class} restore 失败");
            $class::find($id)->forceDelete();
        } else {
            $found->delete();
            $this->assertNull($class::find($id), "{$class} delete 后仍可查到");
        }
    }

    /** 按列类型生成合法属性值；有默认值的可空列跳过（让 DB 默认生效） */
    private function buildAttrs(array $cols): array
    {
        $attrs = [];
        foreach ($cols as $c) {
            $name = $c->Field;
            if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at', 'money', 'score', 'level'], true)) {
                continue;
            }
            if ($c->Null === 'YES' && $c->Default !== null) {
                continue;
            }
            $type = strtolower($c->Type);
            if (str_starts_with($type, 'enum(')) {
                preg_match('/^enum\((.*)\)$/', $type, $m);
                $attrs[$name] = str_getcsv($m[1], ',', "'")[0];
            } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) {
                $attrs[$name] = 1;
            } elseif (preg_match('/^(decimal|float|double)/', $type)) {
                $attrs[$name] = 1.00;
            } elseif (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                $attrs[$name] = '2026-01-01 00:00:00';
            } elseif (str_starts_with($type, 'date')) {
                $attrs[$name] = '2026-01-01';
            } elseif (str_starts_with($type, 'time')) {
                $attrs[$name] = '12:00:00';
            } elseif (str_starts_with($type, 'json')) {
                $attrs[$name] = '{}';
            } elseif (str_starts_with($type, 'year')) {
                $attrs[$name] = 2026;
            } elseif (str_starts_with($type, 'char') || str_starts_with($type, 'varchar')) {
                preg_match('/\((\d+)\)/', $type, $m);
                $len = (int) ($m[1] ?? 255);
                $attrs[$name] = 't' . str_repeat('e', min(max($len - 1, 0), 30));
            } else {
                $attrs[$name] = 'test';
            }
        }
        return $attrs;
    }

    /** 受保护列（$guarded）中 NOT NULL 无默认值的，需直写绕过批量赋值 */
    private function guardedAttrs(array $cols): array
    {
        $guarded = [];
        foreach ($cols as $c) {
            if (in_array($c->Field, ['money', 'score', 'level'], true)
                && $c->Null === 'NO' && $c->Default === null
            ) {
                $guarded[$c->Field] = preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', strtolower($c->Type)) ? 1 : 0;
            }
        }
        return $guarded;
    }

    private function pickUpdatableCol(array $cols): ?string
    {
        foreach ($cols as $c) {
            if (in_array($c->Field, ['id', 'created_at', 'updated_at', 'deleted_at', 'money', 'score', 'level'], true)) {
                continue;
            }
            // 跳过短 varchar/char 列（如 code(3)/hs_code），放不下 'updated-value'(13字符)
            $type = strtolower($c->Type);
            if (preg_match('/^(char|varchar)\((\d+)\)/', $type, $m) && (int) $m[2] < 13) {
                continue;
            }
            // 跳过 decimal 列：回读带精度后缀（如 2 → 2.00000000），字符串比较不适用
            if (preg_match('/^(decimal|float|double)/', $type)) {
                continue;
            }
            return $c->Field;
        }
        return null;
    }
}
