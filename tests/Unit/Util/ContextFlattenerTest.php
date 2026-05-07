<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Util\ContextFlattener;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

final class ContextFlattenerTest extends TestCase
{
    public function testFlattensNestedScalars(): void
    {
        $flat = ContextFlattener::flatten([
            'a'   => ['b' => ['c' => 42]],
            'top' => true,
        ]);

        self::assertSame([
            'a.b.c' => 42,
            'top'   => true,
        ], $flat);
    }

    public function testPreservesModelObjects(): void
    {
        $rows = new TableRows('line', [['line' => '1', 'x' => 'y']]);
        $html = new HtmlContent('<p>Hi</p>');
        $img  = new ImageSource('/tmp/x.png');

        $flat = ContextFlattener::flatten([
            'nested' => [
                'rows' => $rows,
            ],
            'body' => $html,
            'logo' => $img,
        ]);

        self::assertSame($rows, $flat['nested.rows']);
        self::assertSame($html, $flat['body']);
        self::assertSame($img, $flat['logo']);
    }

    public function testSupportsStringable(): void
    {
        $flat = ContextFlattener::flatten([
            'id' => new class implements Stringable {
                public function __toString(): string
                {
                    return '42';
                }
            },
        ]);

        $id = $flat['id'];
        self::assertInstanceOf(Stringable::class, $id);
        self::assertSame('42', (string) $id);
    }

    public function testRejectsUnsupportedObjects(): void
    {
        $this->expectException(InvalidContextValueException::class);

        ContextFlattener::flatten([
            'bad' => new stdClass(),
        ]);
    }
}
