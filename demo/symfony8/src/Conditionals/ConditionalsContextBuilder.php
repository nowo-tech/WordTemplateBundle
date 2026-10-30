<?php

declare(strict_types=1);

namespace App\Conditionals;

use App\Model\InvoiceStatus;
use DateTimeImmutable;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\TableRows;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function in_array;
use function number_format;
use function preg_split;
use function round;
use function str_contains;
use function strcasecmp;
use function strtolower;
use function trim;

/**
 * Turns the conditionals demo form data into a WordTemplateBundle context, using the expressions
 * recommended in docs/USAGE.md "Conditionals by data type", and records every decision for the preview.
 *
 * @phpstan-type FormData array{customer_name?: ?string, is_vip?: bool, terms_accepted?: ?bool, quantity?: ?int, discount?: ?int, total?: float|int|null, country?: ?string, notes?: ?string, status?: ?InvoiceStatus, due_date?: ?DateTimeImmutable, lines?: ?string, plan?: ?string}
 * @phpstan-type Decision array{type: string, value: string, expression: string, blocks: array<string, bool>}
 */
final class ConditionalsContextBuilder
{
    /**
     * Plans with their own block in the template; anything else shows {@code plan_other}.
     */
    private const PLANS = ['basic', 'pro', 'enterprise'];

    /**
     * Expression conditionals of {@code conditionals-tpl.docx} (opening marker text => paragraph inside the block),
     * evaluated by the bundle against the {@code order} map built below. Also read by bin/build-conditionals-template.php.
     */
    public const EXPRESSIONS = [
        'order.total > 100'                                                            => 'Large order (over 100 EUR): shipping is free.',
        "order.is_vip and upper(order.country) == 'ES'"                                => 'Spanish VIP customer: a personal account manager will call you.',
        "order.status in ['draft', 'overdue']"                                         => 'Reminder: this invoice is not paid yet.',
        "order.due_date != null and order.due_date < now() and order.status != 'paid'" => 'Overdue: the due date has passed.',
        '(order.discount ?? 0) > 0'                                                    => 'A discount applies to this order.',
        'length(order.lines) > 1'                                                      => 'Several order lines: they ship together.',
        "trim(default(order.notes, '')) != ''"                                         => 'The customer left notes.',
        "order.plan == 'pro'"                                                          => 'Pro plan: 24/7 support.',
        "order.plan != 'pro'"                                                          => 'Upgrade to Pro for 24/7 support.',
    ];

    /**
     * @param FormData $data
     *
     * @return array{context: array<string, mixed>, decisions: list<Decision>}
     */
    public function build(array $data, DateTimeImmutable $now): array
    {
        $decisions = [];
        $context   = ['customer_name' => trim($data['customer_name'] ?? '') !== '' ? trim($data['customer_name'] ?? '') : 'Customer'];

        // bool — CheckboxType gives true / false.
        $isVip       = ($data['is_vip'] ?? false) === true;
        $decisions[] = $this->decide($context, 'bool', $isVip ? 'true' : 'false', '$isVip / !$isVip', ['vip' => $isVip, 'not_vip' => !$isVip]);

        // ?bool — ChoiceType with a placeholder gives true / false / null.
        $accepted    = $data['terms_accepted'] ?? null;
        $decisions[] = $this->decide($context, '?bool', $accepted === null ? 'null' : ($accepted ? 'true' : 'false'), '$accepted === true / === false / === null', [
            'terms_accepted' => $accepted === true,
            'terms_rejected' => $accepted === false,
            'terms_unknown'  => $accepted === null,
        ]);

        // ?int — IntegerType gives null for an empty field.
        $quantity            = $data['quantity'] ?? null;
        $context['quantity'] = (string) ($quantity ?? 0);
        $decisions[]         = $this->decide($context, '?int', $quantity === null ? 'null' : (string) $quantity, '($quantity ?? 0) > 0', ['has_quantity' => ($quantity ?? 0) > 0]);

        // ?int where 0 is information — "Discount: 0 %" is shown, an empty field is not.
        $discount            = $data['discount'] ?? null;
        $context['discount'] = (string) ($discount ?? '');
        $decisions[]         = $this->decide($context, '?int', $discount === null ? 'null' : (string) $discount, '$discount !== null', ['has_discount' => $discount !== null]);

        // ?float — round to the printed precision before comparing.
        $total            = (float) ($data['total'] ?? 0.0);
        $context['total'] = number_format(round($total, 2), 2, '.', ',');
        $decisions[]      = $this->decide($context, '?float', (string) $total, 'round($total, 2) > 0.0', ['amount_due' => round($total, 2) > 0.0, 'nothing_due' => round($total, 2) <= 0.0]);

        // string — case-insensitive equality.
        $country     = trim($data['country'] ?? '');
        $isDomestic  = strcasecmp($country, 'es') === 0;
        $decisions[] = $this->decide($context, 'string', "'" . $country . "'", "strcasecmp(\$country, 'es') === 0", ['domestic' => $isDomestic, 'foreign' => !$isDomestic]);

        // ?string — blank (only spaces) counts as empty; '0' would count as text.
        $notes            = $data['notes'] ?? null;
        $context['notes'] = trim($notes ?? '');
        $decisions[]      = $this->decide($context, '?string', $notes === null ? 'null' : "'" . $notes . "'", "trim(\$notes ?? '') !== ''", ['has_notes' => trim($notes ?? '') !== '']);

        // backed enum — one block per case (a new case automatically gets its block decision).
        $status       = $data['status'] ?? null;
        $statusBlocks = [];
        foreach (InvoiceStatus::cases() as $case) {
            $statusBlocks['status_' . $case->value] = $status === $case;
        }
        $decisions[] = $this->decide($context, 'enum', $status === null ? 'null' : 'InvoiceStatus::' . $status->name, '$status === $case (one block per case)', $statusBlocks + ['draft' => $status === InvoiceStatus::Draft]);

        // ?DateTimeImmutable + combined condition (AND) flattened into one block.
        $dueDate             = $data['due_date'] ?? null;
        $today               = $now->setTime(0, 0);
        $isOverdue           = $dueDate !== null && $dueDate < $today && $status !== InvoiceStatus::Paid;
        $context['due_date'] = $dueDate?->format('d/m/Y') ?? '';
        $decisions[]         = $this->decide($context, '?DateTimeImmutable', $dueDate?->format('Y-m-d') ?? 'null', '$dueDate !== null && $dueDate < $today && $status !== InvoiceStatus::Paid', ['overdue_notice' => $isOverdue]);

        // array — a table that may be empty: TableRows only when there are rows.
        $lines       = $this->parseLines($data['lines'] ?? '');
        $hasLines    = $lines !== [];
        $decisions[] = $this->decide($context, 'array', count($lines) . ' line(s)', '$lines !== []', ['has_lines' => $hasLines, 'no_lines' => !$hasLines]);
        if ($hasLines) {
            $context['lines'] = new TableRows('line_code', array_map(
                static fn (array $line): array => ['line_code' => $line['code'], 'line_desc' => $line['description']],
                $lines,
            ));
        }

        // free string — "one of N" with a fallback block.
        $plan       = strtolower(trim($data['plan'] ?? ''));
        $planBlocks = [];
        foreach (self::PLANS as $option) {
            $planBlocks['plan_' . $option] = $plan === $option;
        }
        $planBlocks['plan_other'] = !in_array($plan, self::PLANS, true);
        $decisions[]              = $this->decide($context, 'string (one of N)', "'" . $plan . "'", '$plan === $option / !in_array($plan, $plans, true)', $planBlocks);

        // Raw typed values for the expression conditionals of the template ("order.total > 100", …).
        $context['order'] = [
            'total'    => $total,
            'country'  => $country,
            'is_vip'   => $isVip,
            'status'   => $status,
            'due_date' => $dueDate,
            'discount' => $discount,
            'notes'    => $notes,
            'lines'    => array_map(static fn (array $line): string => $line['code'], $lines),
            'plan'     => $plan,
        ];

        return ['context' => $context, 'decisions' => $decisions];
    }

    /**
     * Adds one {@see ConditionalBlock} per block to the context and returns the decision row for the preview.
     *
     * @param array<string, mixed> $context
     * @param array<string, bool> $blocks
     *
     * @return Decision
     */
    private function decide(array &$context, string $type, string $value, string $expression, array $blocks): array
    {
        foreach ($blocks as $blockName => $visible) {
            $context['blocks'][$blockName] = new ConditionalBlock($blockName, $visible);
        }

        return ['type' => $type, 'value' => $value, 'expression' => $expression, 'blocks' => $blocks];
    }

    /**
     * @return list<array{code: string, description: string}>
     */
    private function parseLines(string $raw): array
    {
        $rows = array_filter(
            array_map(trim(...), preg_split('/\R/', $raw) ?: []),
            static fn (string $line): bool => $line !== '',
        );

        return array_values(array_map(
            static function (string $line): array {
                if (!str_contains($line, ':')) {
                    return ['code' => $line, 'description' => ''];
                }
                [$code, $description] = explode(':', $line, 2);

                return ['code' => trim($code), 'description' => trim($description)];
            },
            $rows,
        ));
    }
}
