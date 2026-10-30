<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Expression;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Nowo\WordTemplateBundle\Expression\ExpressionMap;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Expression\UnknownVariableError;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\InvoiceStatus;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\Priority;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

final class ExpressionVariablesTest extends TestCase
{
    public function testExposureRules(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'ACME';
            }
        };

        $vars = ExpressionVariables::fromContext([
            'qty'        => 3,
            'total'      => 1.5,
            'name'       => 'x',
            'flag'       => false,
            'nothing'    => null,
            'company'    => $stringable,
            'status'     => InvoiceStatus::Paid,
            'priority'   => Priority::High,
            'due'        => new DateTimeImmutable('2026-10-30 23:30', new DateTimeZone('Europe/Madrid')),
            'tags'       => ['a', new stdClass(), 'b'],
            'html'       => new HtmlContent('<p>x</p>'),
            'image'      => new ImageSource('/tmp/x.png'),
            'rows'       => new TableRows('c', [['c' => 1]]),
            'object'     => new stdClass(),
            'not-an-id'  => 1,
            0            => 'int key',
            'show_annex' => new ConditionalBlock('annex', true),
            'blocks'     => [new ConditionalBlock('vip', false), new ConditionalBlock('qty', true), new ConditionalBlock('odd.name', true)],
        ]);

        self::assertSame(3, $vars['qty']);
        self::assertSame(1.5, $vars['total']);
        self::assertSame('x', $vars['name']);
        self::assertFalse($vars['flag']);
        self::assertNull($vars['nothing']);
        self::assertSame('ACME', $vars['company']);
        self::assertSame('paid', $vars['status']);
        self::assertSame('High', $vars['priority']);
        self::assertSame('2026-10-30', $vars['due']);
        self::assertSame(['a', 'b'], $vars['tags']);
        self::assertSame([], $vars['blocks']);
        self::assertTrue($vars['annex']);
        self::assertFalse($vars['vip']);
        self::assertSame(3, $vars['qty'], 'a context key wins over a block with the same name');
        foreach (['html', 'image', 'rows', 'object', 'not-an-id', 'show_annex', 'odd.name'] as $hidden) {
            self::assertArrayNotHasKey($hidden, $vars);
        }
        self::assertArrayNotHasKey(0, $vars);
    }

    public function testMapsAreReadOnlyAndNameMissingKeys(): void
    {
        $vars = ExpressionVariables::fromContext(['client' => ['country' => 'ES', 'zip' => null, 'address' => ['city' => 'Madrid'], 5 => 'five']]);
        $map  = $vars['client'];

        self::assertInstanceOf(ExpressionMap::class, $map);
        self::assertSame('ES', $map->__get('country'));
        self::assertSame('ES', $map['country']);
        self::assertSame('five', $map[5]);
        self::assertTrue(isset($map->country));
        self::assertFalse(isset($map->zip));
        self::assertFalse(isset($map['zip']));
        self::assertInstanceOf(ExpressionMap::class, $map->__get('address'));

        try {
            $map->__get('address')->__get('street');
            self::fail('UnknownVariableError expected');
        } catch (UnknownVariableError $e) {
            self::assertSame('client.address.street', $e->variable);
        }

        try {
            self::assertNull($map[7]);
            self::fail('UnknownVariableError expected');
        } catch (UnknownVariableError $e) {
            self::assertSame('client[7]', $e->variable);
        }

        foreach ([
            static function () use ($map): void { $map->country = 'PT'; },
            static function () use ($map): void { $map['country'] = 'PT'; },
            static function () use ($map): void { unset($map['country']); },
        ] as $write) {
            try {
                $write();
                self::fail('LogicException expected');
            } catch (LogicException $e) {
                self::assertSame('Expression variables are read-only.', $e->getMessage());
            }
        }
    }
}
