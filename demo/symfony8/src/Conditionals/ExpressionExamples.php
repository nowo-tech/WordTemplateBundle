<?php

declare(strict_types=1);

namespace App\Conditionals;

use App\Model\InvoiceStatus;
use DateTimeImmutable;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\TableRows;

/**
 * Worked examples of the "/conditionals" page: for each one the text typed in Word, the PHP context (as shown in
 * docs/USAGE.md) and the context really passed to the processor. {@see ExpressionExampleRunner} builds the template
 * from {@see $word} (a line starting with "|" is a table row) and merges it.
 *
 * @phpstan-type Example array{id: string, title: string, note: string, word: list<string>, php: string, context: array<string, mixed>}
 */
final class ExpressionExamples
{
    /**
     * @return list<Example>
     */
    public static function all(DateTimeImmutable $now): array
    {
        $lines = [
            ['line_code' => 'A-1', 'line_desc' => 'Anchor bolts'],
            ['line_code' => 'B-2', 'line_desc' => 'Brackets'],
        ];

        return [
            [
                'id'      => 'named-block',
                'title'   => 'Named block (decided in PHP)',
                'note'    => 'The 1.7 syntax: the end marker repeats the name and a ConditionalBlock decides. Unchanged in 1.8.',
                'word'    => ['Offer for ${client}', '${#if annex}', 'Annex: technical specification.', '${#endif annex}', 'Signed in Madrid.'],
                'php'     => "\$processor->process(\$template, [\n    'client' => 'ACME',\n    'annex'  => new ConditionalBlock('annex', true),\n]);",
                'context' => ['client' => 'ACME', 'annex' => new ConditionalBlock('annex', true)],
            ],
            [
                'id'      => 'expression-true',
                'title'   => 'Expression — true',
                'note'    => 'A nameless ${#endif} makes the block an expression evaluated against the context.',
                'word'    => ['Invoice ${number}', '${#if total > 1000}', 'Orders above 1,000 EUR ship for free.', '${#endif}', 'Thank you.'],
                'php'     => "\$processor->process(\$template, [\n    'number' => 'F-2026-001',\n    'total'  => 1500,\n]);",
                'context' => ['number' => 'F-2026-001', 'total' => 1500],
            ],
            [
                'id'      => 'expression-false',
                'title'   => 'Expression — false',
                'note'    => 'Same template, total below the threshold: the block and its markers are removed.',
                'word'    => ['Invoice ${number}', '${#if total > 1000}', 'Orders above 1,000 EUR ship for free.', '${#endif}', 'Thank you.'],
                'php'     => "\$processor->process(\$template, [\n    'number' => 'F-2026-002',\n    'total'  => 900,\n]);",
                'context' => ['number' => 'F-2026-002', 'total' => 900],
            ],
            [
                'id'      => 'functions',
                'title'   => 'Functions and operators',
                'note'    => 'Twig-like functions are written as calls (upper(x), not x|upper). Word smart quotes are fine.',
                'word'    => ['Customer ${client.name}', "\${#if upper(client.country) == 'ES' and length(lines) > 1}", 'Domestic order with several lines: Spanish VAT applies.', '${#endif}', "\${#if client.email ends with '.org'}", 'Non-profit discount available.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'client' => ['name' => 'ACME', 'country' => 'es', 'email' => 'info@acme.com'],\n    'lines'  => ['A-1', 'B-2'],\n]);",
                'context' => ['client' => ['name' => 'ACME', 'country' => 'es', 'email' => 'info@acme.com'], 'lines' => ['A-1', 'B-2']],
            ],
            [
                'id'      => 'nested',
                'title'   => 'Nested blocks',
                'note'    => 'Evaluated outside-in: client.vip is never read when client is null.',
                'word'    => ['${#if client != null}', 'Customer: ${client.name}', '${#if client.vip}', 'Priority support included.', '${#endif}', '${#endif}', 'End of offer.'],
                'php'     => "\$processor->process(\$template, [\n    'client' => ['name' => 'ACME', 'vip' => true],\n]);",
                'context' => ['client' => ['name' => 'ACME', 'vip' => true]],
            ],
            [
                'id'      => 'else',
                'title'   => '"Else": two opposite expressions',
                'note'    => 'There is no ${#else}: write the opposite condition. Enums compare by value, dates print as Y-m-d.',
                'word'    => ['Invoice ${number}', "\${#if status == 'paid'}", 'Thank you, this invoice is paid.', '${#endif}', "\${#if status != 'paid'}", 'Please pay before ${due_date}.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'number'   => 'F-7',\n    'status'   => InvoiceStatus::Overdue,\n    'due_date' => new DateTimeImmutable('2026-11-15'),\n]);",
                'context' => ['number' => 'F-7', 'status' => InvoiceStatus::Overdue, 'due_date' => new DateTimeImmutable('2026-11-15')],
            ],
            [
                'id'      => 'one-of-n',
                'title'   => 'One of N with a fallback',
                'note'    => 'One block per option and a "not in" fallback for unexpected values.',
                'word'    => ["\${#if plan == 'basic'}", 'Plan: Basic.', '${#endif}', "\${#if plan == 'pro'}", 'Plan: Pro — 24/7 support.', '${#endif}', "\${#if plan not in ['basic', 'pro']}", 'Plan not recognised — please contact sales.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'plan' => 'pro',\n]);",
                'context' => ['plan' => 'pro'],
            ],
            [
                'id'      => 'table',
                'title'   => 'A table only when there are rows',
                'note'    => 'TableRows needs at least one row: add it only when the list is not empty, and pass the list for the expression.',
                'word'    => ['Order lines', '${#if lines != []}', '| ${line_code} | ${line_desc} |', '${#endif}', '${#if lines == []}', 'No items in this order.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'lines' => \$lines,\n    'rows'  => new TableRows('line_code', \$lines), // only when \$lines !== []\n]);",
                'context' => ['lines' => $lines, 'rows' => new TableRows('line_code', $lines)],
            ],
            [
                'id'      => 'table-empty',
                'title'   => 'A table only when there are rows — empty list',
                'note'    => 'Same template with no lines: the table is removed and the "no items" text is kept.',
                'word'    => ['Order lines', '${#if lines != []}', '| ${line_code} | ${line_desc} |', '${#endif}', '${#if lines == []}', 'No items in this order.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'lines' => [],\n]);",
                'context' => ['lines' => []],
            ],
            [
                'id'      => 'dates',
                'title'   => 'Dates with now() and date()',
                'note'    => 'now() and date() read the Symfony clock in the configured time zone; dates compare as Y-m-d strings.',
                'word'    => ['Due date: ${due_date}', '${#if date_diff_days(now(), due_date) <= 7}', 'Reminder: payment is due within a week.', '${#endif}', "\${#if due_date > date('+30 days')}", 'Long payment term.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'due_date' => \$clock->now()->modify('+5 days'),\n]);",
                'context' => ['due_date' => $now->modify('+5 days')],
            ],
            [
                'id'      => 'error',
                'title'   => 'Error: a typo in a variable name',
                'note'    => 'Default on_error: exception. The message names the expression and the unknown variable.',
                'word'    => ['${#if totl > 0}', 'Amount due.', '${#endif}'],
                'php'     => "\$processor->process(\$template, [\n    'total' => 5,\n]);",
                'context' => ['total' => 5],
            ],
        ];
    }
}
