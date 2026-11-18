<?php

namespace websvc\audit\tests\unit\components;

use PHPUnit\Framework\TestCase;
use websvc\audit\components\ValueFormatter;

class ValueFormatterTest extends TestCase
{
    public function testFormatsScalarsArraysAndNull(): void
    {
        $this->assertNull(ValueFormatter::format(null));
        $this->assertSame('true', ValueFormatter::format(true));
        $this->assertSame('false', ValueFormatter::format(false));
        $this->assertSame('12', ValueFormatter::format(12));
        $this->assertSame('São Brás', ValueFormatter::format('São Brás'));
        $this->assertSame('{"a":1,"b":"x/y"}', ValueFormatter::format(['a' => 1, 'b' => 'x/y']));
    }

    public function testFlattenUsesDottedKeysAndSkipsEmptyGroups(): void
    {
        $flat = ValueFormatter::flatten([
            'get' => [],
            'post' => ['Invoice' => ['status' => 'Paid', 'lines' => [3, 4]], 'password' => '***', 'note' => null],
            'files' => [],
        ]);

        $this->assertSame(
            [
                'post.Invoice.status' => 'Paid',
                'post.Invoice.lines.0' => '3',
                'post.Invoice.lines.1' => '4',
                'post.password' => '***',
                'post.note' => null,
            ],
            $flat
        );
    }

    public function testFlattenOfNothingIsEmpty(): void
    {
        $this->assertSame([], ValueFormatter::flatten(['args' => []]));
    }
}
