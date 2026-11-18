<?php

namespace websvc\audit\tests\unit\components;

use PHPUnit\Framework\TestCase;
use websvc\audit\components\ParamsMasker;

class ParamsMaskerTest extends TestCase
{
    private const MASK_KEYS = ['password', 'token', 'secret', 'csrf', 'auth_key', 'api_key'];

    public function testMasksNestedKeysCaseInsensitive(): void
    {
        $masked = (new ParamsMasker(self::MASK_KEYS, 65536))->mask([
            'LoginForm' => ['username' => 'ana', 'Password' => 'x'],
            '_csrf-backend' => 't',
        ]);

        $this->assertSame(
            ['LoginForm' => ['username' => 'ana', 'Password' => '***'], '_csrf-backend' => '***'],
            $masked
        );
    }

    public function testMatchingKeyMasksWholeSubtree(): void
    {
        $masked = (new ParamsMasker(self::MASK_KEYS, 65536))->mask(['token' => ['a' => 1]]);

        $this->assertSame(['token' => '***'], $masked);
    }

    public function testMasksConsoleOptionValues(): void
    {
        $masked = (new ParamsMasker(self::MASK_KEYS, 65536))->maskArgs(['import', '--api_key=abc', '--limit=5']);

        $this->assertSame(['import', '--api_key=***', '--limit=5'], $masked);
    }

    public function testEncodeReturnsMarkerWhenTooLarge(): void
    {
        $json = (new ParamsMasker(self::MASK_KEYS, 100))->encode(['big' => str_repeat('a', 500), 'x' => 1]);

        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['_truncated']);
        $this->assertGreaterThan(100, $decoded['_bytes']);
        $this->assertSame(['big', 'x'], $decoded['_keys']);
    }

    public function testEncodeHandlesInvalidUtf8(): void
    {
        $json = (new ParamsMasker(self::MASK_KEYS, 65536))->encode(['a' => "\xB1"]);

        $this->assertNotNull(json_decode($json, true));
    }

    public function testDescribeFilesFlattensSingleAndMultiple(): void
    {
        $files = [
            'doc' => ['name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/a', 'error' => 0, 'size' => 10],
            'Model' => [
                'name' => ['files' => ['a.png', 'b.png']],
                'type' => ['files' => ['image/png', 'image/png']],
                'tmp_name' => ['files' => ['/tmp/b', '/tmp/c']],
                'error' => ['files' => [0, 4]],
                'size' => ['files' => [1, 2]],
            ],
        ];

        $this->assertSame(
            [
                ['field' => 'doc', 'name' => 'a.pdf', 'size' => 10, 'error' => 0],
                ['field' => 'Model[files][0]', 'name' => 'a.png', 'size' => 1, 'error' => 0],
                ['field' => 'Model[files][1]', 'name' => 'b.png', 'size' => 2, 'error' => 4],
            ],
            ParamsMasker::describeFiles($files)
        );
    }
}
