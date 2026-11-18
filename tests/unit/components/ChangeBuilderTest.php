<?php

namespace websvc\audit\tests\unit\components;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use websvc\audit\components\ChangeBuilder;
use yii\db\Expression;

class ChangeBuilderTest extends TestCase
{
    public function testInsertRecordsNonNullNewValues(): void
    {
        $result = (new ChangeBuilder())->build('insert', [], ['id' => 1, 'title' => 'A', 'body' => null]);

        $this->assertSame(
            ['id' => ['old' => null, 'new' => 1], 'title' => ['old' => null, 'new' => 'A']],
            $result
        );
    }

    public function testUpdateRecordsOnlyRealChanges(): void
    {
        $result = (new ChangeBuilder())->build(
            'update',
            ['title' => 'A', 'status' => 1, 'body' => null],
            ['title' => 'B', 'status' => '1', 'body' => '']
        );

        $this->assertSame(
            ['title' => ['old' => 'A', 'new' => 'B'], 'body' => ['old' => null, 'new' => '']],
            $result
        );
    }

    public function testNumericValuesCompareAsNumbers(): void
    {
        $this->assertNull((new ChangeBuilder())->build('update', ['price' => '1.50'], ['price' => 1.5]));
    }

    public function testIgnoredOnlyUpdateReturnsNull(): void
    {
        $builder = new ChangeBuilder(['updated_at']);

        $this->assertNull($builder->build('update', ['updated_at' => 1], ['updated_at' => 2]));
    }

    public function testDeleteRecordsNonNullOldValues(): void
    {
        $result = (new ChangeBuilder())->build('delete', ['id' => 3, 'title' => 'A', 'body' => null], []);

        $this->assertSame(
            ['id' => ['old' => 3, 'new' => null], 'title' => ['old' => 'A', 'new' => null]],
            $result
        );
    }

    public function testMaskedAttributesHideValues(): void
    {
        $builder = new ChangeBuilder([], ['password_hash']);

        $this->assertSame(
            ['password_hash' => ['old' => '***', 'new' => '***']],
            $builder->build('update', ['password_hash' => 'x'], ['password_hash' => 'y'])
        );
        $this->assertSame(
            ['password_hash' => ['old' => null, 'new' => '***']],
            $builder->build('insert', [], ['password_hash' => 'x'])
        );
    }

    public function testNormalizesNonScalarValues(): void
    {
        $result = (new ChangeBuilder())->build('insert', [], [
            'date' => new DateTimeImmutable('2026-11-17 10:00:00'),
            'expression' => new Expression('NOW()'),
            'list' => ['a' => 1],
            'binary' => "\xB1\x31",
        ]);

        $this->assertSame('2026-11-17 10:00:00', $result['date']['new']);
        $this->assertSame('NOW()', $result['expression']['new']);
        $this->assertSame(['a' => 1], $result['list']['new']);
        $this->assertSame('[binary 2 bytes]', $result['binary']['new']);
    }

    public function testResultIsJsonEncodable(): void
    {
        $result = (new ChangeBuilder())->build('update', ['data' => 'plain'], ['data' => "\xB1\x31"]);

        $json = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertJson($json);
    }
}
