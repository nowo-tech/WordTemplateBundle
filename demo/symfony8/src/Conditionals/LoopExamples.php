<?php

declare(strict_types=1);

namespace App\Conditionals;

/**
 * Worked examples of the "/loops" page (same shape as {@see ExpressionExamples}; a line starting with "|" is a table
 * row, consecutive rows form one table).
 *
 * @phpstan-import-type Example from ExpressionExamples
 */
final class LoopExamples
{
    /**
     * @return list<Example>
     */
    public static function all(): array
    {
        $lines = [
            ['name' => 'Anchor bolts M12', 'qty' => 200, 'amount' => 360.0],
            ['name' => 'Steel brackets', 'qty' => 40, 'amount' => 520.0],
            ['name' => 'Crane rental (1 day)', 'qty' => 1, 'amount' => 450.0],
        ];

        return [
            [
                'id'    => 'loop-invoice',
                'title' => 'Invoice: one table row per line',
                'note'  => 'The markers sit in rows of their own: the rows between them are repeated and the marker rows removed. A row-level ${#if} adds the discount row only when there is one.',
                'word'  => [
                    'Invoice ${number} — ${client}',
                    '| # | Item | Qty | Amount |',
                    '| ${#for line in lines} |',
                    '| ${loop.index} | ${line.name} | ${line.qty} | ${line.amount} |',
                    '| ${#endfor} |',
                    '| ${#if discount > 0} |',
                    '|  | Discount |  | -${discount} |',
                    '| ${#endif} |',
                    '|  | Total |  | ${total} |',
                    '${#if total > 1000}',
                    'Free shipping: the order is above 1,000 EUR.',
                    '${#endif}',
                ],
                'php'     => "\$processor->process(\$template, [\n    'number'   => 'F-2026-042',\n    'client'   => 'ACME Industries',\n    'lines'    => [\n        ['name' => 'Anchor bolts M12', 'qty' => 200, 'amount' => 360.0],\n        ['name' => 'Steel brackets', 'qty' => 40, 'amount' => 520.0],\n        ['name' => 'Crane rental (1 day)', 'qty' => 1, 'amount' => 450.0],\n    ],\n    'discount' => 30,\n    'total'    => 1300,\n]);",
                'context' => ['number' => 'F-2026-042', 'client' => 'ACME Industries', 'lines' => $lines, 'discount' => 30, 'total' => 1300],
            ],
            [
                'id'      => 'loop-paragraphs',
                'title'   => 'Paragraphs and loop variables',
                'note'    => 'loop.index, loop.length, loop.first and loop.last work like in Twig; the same names are variables of ${#if} blocks in the body.',
                'word'    => ['Delivery notes', '${#for note in notes}', '${loop.index}/${loop.length}: ${note}', '${#if loop.last}', '(end of notes)', '${#endif}', '${#endfor}'],
                'php'     => "\$processor->process(\$template, [\n    'notes' => ['Fragile goods', 'Deliver on Monday', 'Call before arrival'],\n]);",
                'context' => ['notes' => ['Fragile goods', 'Deliver on Monday', 'Call before arrival']],
            ],
            [
                'id'      => 'loop-nested',
                'title'   => 'Nested loops with a condition',
                'note'    => 'The inner loop iterates over a list of the current order; loop.parent is the enclosing loop.',
                'word'    => ['${#for order in orders}', 'Order ${order.id}', '${#if order.urgent}', 'Ship today.', '${#endif}', '${#for line in order.lines}', '${loop.parent.index}.${loop.index} ${line}', '${#endfor}', '${#endfor}'],
                'php'     => "\$processor->process(\$template, [\n    'orders' => [\n        ['id' => 'A-7', 'urgent' => true, 'lines' => ['Bolts', 'Nuts']],\n        ['id' => 'B-8', 'urgent' => false, 'lines' => ['Crane']],\n    ],\n]);",
                'context' => ['orders' => [['id' => 'A-7', 'urgent' => true, 'lines' => ['Bolts', 'Nuts']], ['id' => 'B-8', 'urgent' => false, 'lines' => ['Crane']]]],
            ],
            [
                'id'      => 'loop-map',
                'title'   => 'Map keys and values',
                'note'    => '${#for key, value in map} iterates a map; functions such as keys() or slice() can build the iterable.',
                'word'    => ['Coverage', '${#for code, limit in limits}', '${code}: up to ${limit} EUR', '${#endfor}'],
                'php'     => "\$processor->process(\$template, [\n    'limits' => ['Fire' => 100000, 'Theft' => 25000, 'Flood' => 50000],\n]);",
                'context' => ['limits' => ['Fire' => 100000, 'Theft' => 25000, 'Flood' => 50000]],
            ],
            [
                'id'      => 'loop-empty',
                'title'   => 'Empty list',
                'note'    => 'An empty list removes the whole loop; an opposite ${#if} prints a fallback.',
                'word'    => ['Order lines', '${#for line in lines}', '${line}', '${#endfor}', '${#if length(lines) == 0}', 'No items in this order.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'lines' => [],\n]);",
                'context' => ['lines' => []],
            ],
        ];
    }
}
