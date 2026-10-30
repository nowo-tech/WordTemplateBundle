<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\Contract;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\Customer;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\Guarantor;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\Invoice;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\InvoiceStatus;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\Order;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\OrderLine;
use Nowo\WordTemplateBundle\Util\ContextFlattener;
use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use TypeError;
use ValueError;
use ZipArchive;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function dirname;
use function file_get_contents;
use function html_entity_decode;
use function implode;
use function in_array;
use function is_string;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function strtolower;
use function trim;
use function var_export;

use const ENT_QUOTES;
use const ENT_XML1;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;
use const FILTER_VALIDATE_INT;

/**
 * Executable companion of docs/USAGE.md "Conditionals by data type".
 *
 * Every expression of the per-type tables is listed in {@see self::documentedExpressions()} with the exact
 * source text shown in the docs (asserted to be present in USAGE.md) and a closure with the same code, run
 * against sample values. The pattern sections ("else", one of N, tables, placement, missing blocks) are
 * rendered through a real .docx built with PHPWord.
 */
final class ConditionalsUsageDocTest extends TestCase
{
    /**
     * @param list<mixed> $args
     */
    #[DataProvider('documentedExpressions')]
    public function testDocumentedExpression(string $snippet, Closure $expression, array $args, bool $expected): void
    {
        self::assertStringContainsString($snippet, $this->usage(), 'Snippet missing from docs/USAGE.md: ' . $snippet);
        self::assertSame($expected, $expression(...$args), $snippet);
    }

    /**
     * @return iterable<string, array{string, Closure, list<mixed>, bool}>
     */
    public static function documentedExpressions(): iterable
    {
        // bool / ?bool
        yield 'bool true' => ['`$isVip`', static fn (bool $isVip): bool => $isVip, [true], true];
        yield 'bool false' => ['`$isVip`', static fn (bool $isVip): bool => $isVip, [false], false];
        yield 'bool negated' => ['`!$isVip`', static fn (bool $isVip): bool => !$isVip, [true], false];
        yield '?bool true' => ['`$accepted === true`', static fn (?bool $accepted): bool => $accepted === true, [true], true];
        yield '?bool null is hidden' => ['`$accepted === true`', static fn (?bool $accepted): bool => $accepted === true, [null], false];
        yield '?bool false' => ['`$accepted === false`', static fn (?bool $accepted): bool => $accepted === false, [false], true];
        yield '?bool null not false' => ['`$accepted === false`', static fn (?bool $accepted): bool => $accepted === false, [null], false];
        yield '?bool unknown' => ['`$accepted === null`', static fn (?bool $accepted): bool => $accepted === null, [null], true];

        // int / ?int
        yield 'int zero' => ['`$qty !== 0`', static fn (int $qty): bool => $qty !== 0, [0], false];
        yield 'int negative non-zero' => ['`$qty !== 0`', static fn (int $qty): bool => $qty !== 0, [-2], true];
        yield 'int negative not positive' => ['`$qty > 0`', static fn (int $qty): bool => $qty > 0, [-2], false];
        yield 'int positive' => ['`$qty > 0`', static fn (int $qty): bool => $qty > 0, [3], true];
        yield 'int specific value' => ['`$seats === 2`', static fn (int $seats): bool => $seats === 2, [2], true];
        yield 'int other value' => ['`$seats === 2`', static fn (int $seats): bool => $seats === 2, [3], false];
        yield 'int range below' => ['`$age >= 18 && $age < 65`', static fn (int $age): bool => $age >= 18 && $age < 65, [17], false];
        yield 'int range low edge' => ['`$age >= 18 && $age < 65`', static fn (int $age): bool => $age >= 18 && $age < 65, [18], true];
        yield 'int range high edge' => ['`$age >= 18 && $age < 65`', static fn (int $age): bool => $age >= 18 && $age < 65, [65], false];
        yield '?int zero is set' => ['`$discount !== null`', static fn (?int $discount): bool => $discount !== null, [0], true];
        yield '?int null' => ['`$discount !== null`', static fn (?int $discount): bool => $discount !== null, [null], false];
        yield '?int positive zero' => ['`($discount ?? 0) > 0`', static fn (?int $discount): bool => ($discount ?? 0) > 0, [0], false];
        yield '?int positive null' => ['`($discount ?? 0) > 0`', static fn (?int $discount): bool => ($discount ?? 0) > 0, [null], false];
        yield '?int positive five' => ['`($discount ?? 0) > 0`', static fn (?int $discount): bool => ($discount ?? 0) > 0, [5], true];
        yield 'pitfall !empty hides 0' => ['`!empty($discount)`', static fn (mixed $discount): bool => !empty($discount), [0], false];
        $positiveInt        = static fn (string $raw): bool => filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
        $positiveIntSnippet = "`filter_var(\$raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false`";
        yield 'numeric string positive' => [$positiveIntSnippet, $positiveInt, ['12'], true];
        yield 'numeric string zero' => [$positiveIntSnippet, $positiveInt, ['0'], false];
        yield 'numeric string garbage' => [$positiveIntSnippet, $positiveInt, ['12abc'], false];
        yield 'pitfall string zero !== 0' => ["`\$row['qty'] !== 0`", static fn (array $row): bool => $row['qty'] !== 0, [['qty' => '0']], true];
        yield 'normalised string zero' => ["`(int) \$row['qty'] !== 0`", static fn (array $row): bool => (int) $row['qty'] !== 0, [['qty' => '0']], false];
        yield 'pitfall int cast of abc' => ["`(int) 'abc'` is `0`", static fn (string $raw): bool => (int) $raw === 0, ['abc'], true];
        yield 'pitfall int cast of 12abc' => ["`(int) '12abc'` is `12`", static fn (string $raw): bool => (int) $raw === 12, ['12abc'], true];

        // float
        yield 'float noise not positive' => ['`round($total, 2) > 0.0`', static fn (float $total): bool => round($total, 2) > 0.0, [0.1 + 0.2 - 0.3], false];
        yield 'float one cent positive' => ['`round($total, 2) > 0.0`', static fn (float $total): bool => round($total, 2) > 0.0, [0.01], true];
        yield 'float rounds to minus zero' => ['`round($total, 2) !== 0.0`', static fn (float $total): bool => round($total, 2) !== 0.0, [-0.004], false];
        yield 'float negative cent non-zero' => ['`round($total, 2) !== 0.0`', static fn (float $total): bool => round($total, 2) !== 0.0, [-0.01], true];
        yield 'float tolerance equal' => ['`abs($paid - $due) < 0.005`', static fn (float $paid, float $due): bool => abs($paid - $due) < 0.005, [0.1 + 0.2, 0.3], true];
        yield 'float tolerance different' => ['`abs($paid - $due) < 0.005`', static fn (float $paid, float $due): bool => abs($paid - $due) < 0.005, [10.0, 10.01], false];
        yield 'pitfall float strict equality' => ['`0.1 + 0.2 === 0.3` is `false`', static fn (float $a, float $b, float $c): bool => $a + $b === $c, [0.1, 0.2, 0.3], false];
        yield 'pitfall float noise positive' => ['`0.1 + 0.2 - 0.3 > 0.0` is `true`', static fn (float $a, float $b, float $c): bool => $a + $b - $c > 0.0, [0.1, 0.2, 0.3], true];
        yield 'decimal string positive' => ['`(float) $amount > 0.0`', static fn (string $amount): bool => (float) $amount > 0.0, ['0.00'], false];
        yield 'pitfall decimal string truthy' => ["`'0.00'` is truthy", static fn (string $amount): bool => (bool) $amount, ['0.00'], true];

        // string / ?string
        yield 'string empty' => ["`\$notes !== ''`", static fn (string $notes): bool => $notes !== '', [''], false];
        yield 'string space not empty' => ["`\$notes !== ''`", static fn (string $notes): bool => $notes !== '', [' '], true];
        yield 'string blank' => ["`trim(\$notes) !== ''`", static fn (string $notes): bool => trim($notes) !== '', [" \t"], false];
        yield '?string null blank' => ["`trim(\$notes ?? '') !== ''`", static fn (?string $notes): bool => trim($notes ?? '') !== '', [null], false];
        yield '?string text' => ["`trim(\$notes ?? '') !== ''`", static fn (?string $notes): bool => trim($notes ?? '') !== '', ['See annex'], true];
        yield 'string code zero is present' => ["`\$code !== ''`", static fn (string $code): bool => $code !== '', ['0'], true];
        yield 'pitfall !empty of zero string' => ["`!empty('0')`", static fn (string $code): bool => !empty($code), ['0'], false];
        yield 'pitfall bool cast of zero string' => ["`(bool) '0'`", static fn (string $code): bool => (bool) $code, ['0'], false];
        $boolish        = static fn (mixed $flag): bool => filter_var($flag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;
        $boolishSnippet = '`filter_var($flag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true`';
        yield 'bool-like yes with spaces' => [$boolishSnippet, $boolish, [' Yes '], true];
        yield 'bool-like on' => [$boolishSnippet, $boolish, ['on'], true];
        yield 'bool-like one' => [$boolishSnippet, $boolish, ['1'], true];
        yield 'bool-like TRUE' => [$boolishSnippet, $boolish, ['TRUE'], true];
        yield 'bool-like false' => [$boolishSnippet, $boolish, ['false'], false];
        yield 'bool-like no' => [$boolishSnippet, $boolish, ['no'], false];
        yield 'bool-like unknown' => [$boolishSnippet, $boolish, ['maybe'], false];
        yield 'pitfall bool cast of false string' => ["`(bool) 'false'`", static fn (string $flag): bool => (bool) $flag, ['false'], true];
        yield 'pitfall bool cast of no string' => ["`(bool) 'no'`", static fn (string $flag): bool => (bool) $flag, ['no'], true];
        yield 'pitfall bool cast of off string' => ["`(bool) 'off'`", static fn (string $flag): bool => (bool) $flag, ['off'], true];
        yield 'strcasecmp equal' => ["`strcasecmp(\$country, 'es') === 0`", static fn (string $country): bool => strcasecmp($country, 'es') === 0, ['ES'], true];
        yield 'strcasecmp different' => ["`strcasecmp(\$country, 'es') === 0`", static fn (string $country): bool => strcasecmp($country, 'es') === 0, ['PT'], false];
        yield 'mb_strtolower equal' => ["`mb_strtolower(\$city) === 'málaga'`", static fn (string $city): bool => mb_strtolower($city) === 'málaga', ['MÁLAGA'], true];
        yield 'str_contains match' => ["`str_contains(\$subject, 'URGENT')`", static fn (string $subject): bool => str_contains($subject, 'URGENT'), ['Re: URGENT renewal'], true];
        yield 'str_contains is case-sensitive' => ["`str_contains(\$subject, 'URGENT')`", static fn (string $subject): bool => str_contains($subject, 'URGENT'), ['urgent renewal'], false];
        yield 'stripos at start' => ["`stripos(\$subject, 'urgent') !== false`", static fn (string $subject): bool => stripos($subject, 'urgent') !== false, ['Urgent: renewal'], true];
        yield 'pitfall stripos truthiness at start' => ["`if (stripos(\$subject, 'urgent'))`", static fn (string $subject): bool => (bool) stripos($subject, 'urgent'), ['Urgent: renewal'], false];
        yield 'starts with' => ["`str_starts_with(\$iban, 'ES')`", static fn (string $iban): bool => str_starts_with($iban, 'ES'), ['ES00 TEST'], true];
        yield 'ends with' => ["`str_ends_with(\$email, '@example.com')`", static fn (string $email): bool => str_ends_with($email, '@example.com'), ['jane@example.com'], true];
        yield 'regex match' => ["`preg_match('/^\\d{5}\$/', \$zip) === 1`", static fn (string $zip): bool => preg_match('/^\d{5}$/', $zip) === 1, ['28001'], true];
        yield 'regex no match' => ["`preg_match('/^\\d{5}\$/', \$zip) === 1`", static fn (string $zip): bool => preg_match('/^\d{5}$/', $zip) === 1, ['2800'], false];

        // null / optional values
        yield 'optional object present' => ['`$guarantor !== null`', static fn (?Guarantor $guarantor): bool => $guarantor !== null, [new Guarantor(false)], true];
        yield 'optional object missing' => ['`$guarantor !== null`', static fn (?Guarantor $guarantor): bool => $guarantor !== null, [null], false];
        yield 'null-safe property true' => ['`$guarantor?->isCompany() === true`', static fn (?Guarantor $guarantor): bool => $guarantor?->isCompany() === true, [new Guarantor(true)], true];
        yield 'null-safe property on null' => ['`$guarantor?->isCompany() === true`', static fn (?Guarantor $guarantor): bool => $guarantor?->isCompany() === true, [null], false];
        $optionAnnex = static fn (array $options): bool => ($options['annex'] ?? false) === true;
        yield 'options missing key' => ["`(\$options['annex'] ?? false) === true`", $optionAnnex, [[]], false];
        yield 'options true' => ["`(\$options['annex'] ?? false) === true`", $optionAnnex, [['annex' => true]], true];
        yield 'options string one is not true' => ["`(\$options['annex'] ?? false) === true`", $optionAnnex, [['annex' => '1']], false];
        yield 'coalesce keeps explicit zero' => ["`(\$options['copies'] ?? 1) > 1`", static fn (array $options): bool => ($options['copies'] ?? 1) > 1, [['copies' => 0]], false];
        yield 'array_key_exists with null' => ["`array_key_exists('iban', \$payment)`", static fn (array $payment): bool => array_key_exists('iban', $payment), [['iban' => null]], true];
        yield 'isset with null' => ["`isset(\$payment['iban'])`", static fn (array $payment): bool => isset($payment['iban']), [['iban' => null]], false];

        // arrays
        yield 'array empty' => ['`$lines !== []`', static fn (array $lines): bool => $lines !== [], [[]], false];
        yield 'array non-empty' => ['`$lines !== []`', static fn (array $lines): bool => $lines !== [], [[['code' => 'A']]], true];
        yield 'array count threshold' => ['`count($lines) > 3`', static fn (array $lines): bool => count($lines) > 3, [[1, 2, 3]], false];
        yield 'in_array strict' => ["`in_array('fire', \$covers, true)`", static fn (array $covers): bool => in_array('fire', $covers, true), [['theft', 'fire']], true];
        yield 'array any match' => [
            "`array_filter(\$lines, static fn (array \$line): bool => \$line['qty'] > 10) !== []`",
            static fn (array $lines): bool => array_filter($lines, static fn (array $line): bool => $line['qty'] > 10) !== [],
            [[['qty' => 2], ['qty' => 12]]],
            true,
        ];
        yield 'pitfall loose in_array' => ["`in_array('10', ['1e1'])` is `true`", static fn (string $needle, array $haystack): bool => in_array($needle, $haystack), ['10', ['1e1']], true];
        yield 'strict in_array' => ["`in_array('10', ['1e1'])` is `true`", static fn (string $needle, array $haystack): bool => in_array($needle, $haystack, true), ['10', ['1e1']], false];

        // DateTimeInterface (now = 2026-10-30 12:00 Europe/Madrid)
        $now   = new DateTimeImmutable('2026-10-30 12:00:00', new DateTimeZone('Europe/Madrid'));
        $today = $now->setTime(0, 0);
        yield 'today snippet' => ['$today = $now->setTime(0, 0);', static fn (DateTimeImmutable $today): bool => $today->format('H:i') === '00:00', [$today], true];
        yield 'due yesterday' => ['`$dueDate < $today`', static fn (DateTimeImmutable $dueDate, DateTimeImmutable $today): bool => $dueDate < $today, [new DateTimeImmutable('2026-10-29 18:00', new DateTimeZone('Europe/Madrid')), $today], true];
        yield 'due earlier today is not past' => ['`$dueDate < $today`', static fn (DateTimeImmutable $dueDate, DateTimeImmutable $today): bool => $dueDate < $today, [new DateTimeImmutable('2026-10-30 08:00', new DateTimeZone('Europe/Madrid')), $today], false];
        yield 'starts in future' => ['`$startsAt > $now`', static fn (DateTimeImmutable $startsAt, DateTimeImmutable $now): bool => $startsAt > $now, [$now->modify('+1 hour'), $now], true];
        $sameDay = static fn (DateTimeImmutable $date, DateTimeImmutable $now): bool => $date->format('Y-m-d') === $now->format('Y-m-d');
        yield 'same day' => ["`\$date->format('Y-m-d') === \$now->format('Y-m-d')`", $sameDay, [new DateTimeImmutable('2026-10-30 23:59', new DateTimeZone('Europe/Madrid')), $now], true];
        $lateUtc = new DateTimeImmutable('2026-10-30 23:30', new DateTimeZone('UTC'));
        $lateNow = new DateTimeImmutable('2026-10-31 00:45', new DateTimeZone('Europe/Madrid'));
        yield 'pitfall same day across time zones' => ["`\$date->format('Y-m-d') === \$now->format('Y-m-d')`", $sameDay, [$lateUtc, $lateNow], false];
        yield 'same day after setTimezone' => ['`$date->setTimezone($now->getTimezone())`', static fn (DateTimeImmutable $date, DateTimeImmutable $now): bool => $date->setTimezone($now->getTimezone())->format('Y-m-d') === $now->format('Y-m-d'), [$lateUtc, $lateNow], true];
        $expiresSoon = static fn (DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool => $expiresAt <= $now->modify('+30 days');
        yield 'expires within 30 days' => ["`\$expiresAt <= \$now->modify('+30 days')`", $expiresSoon, [$now->modify('+10 days'), $now], true];
        yield 'expires later' => ["`\$expiresAt <= \$now->modify('+30 days')`", $expiresSoon, [$now->modify('+31 days'), $now], false];
        yield 'optional date set' => ['`$signedAt !== null`', static fn (?DateTimeImmutable $signedAt): bool => $signedAt !== null, [null], false];

        // backed enums
        $draft = new Invoice('F-1', InvoiceStatus::Draft, $now);
        $paid  = new Invoice('F-2', InvoiceStatus::Paid, $now);
        yield 'enum one case' => ['`$invoice->status === InvoiceStatus::Paid`', static fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Paid, [$paid], true];
        yield 'enum one case other' => ['`$invoice->status === InvoiceStatus::Paid`', static fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Paid, [$draft], false];
        $anyOf = static fn (Invoice $invoice): bool => in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Overdue], true);
        yield 'enum any of' => ['`in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Overdue], true)`', $anyOf, [$draft], true];
        yield 'enum any of other' => ['`in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Overdue], true)`', $anyOf, [$paid], false];
        $fromRaw = static fn (string $raw): bool => InvoiceStatus::tryFrom($raw) === InvoiceStatus::Paid;
        yield 'enum tryFrom' => ['`InvoiceStatus::tryFrom($raw) === InvoiceStatus::Paid`', $fromRaw, ['paid'], true];
        yield 'enum tryFrom wrong case' => ['`InvoiceStatus::tryFrom($raw) === InvoiceStatus::Paid`', $fromRaw, ['PAID'], false];
        yield 'enum tryFrom unknown' => ['`InvoiceStatus::tryFrom($raw) === InvoiceStatus::Paid`', $fromRaw, ['unknown'], false];
        yield 'pitfall enum vs backing value' => ["`\$status === 'paid'`", static fn (mixed $status): bool => $status === 'paid', [InvoiceStatus::Paid], false];
        yield 'enum backing value' => ["`\$status->value === 'paid'`", static fn (InvoiceStatus $status): bool => $status->value === 'paid', [InvoiceStatus::Paid], true];

        // Doctrine entities and relations
        yield 'relation set' => ['`$contract->getGuarantor() !== null`', static fn (Contract $contract): bool => $contract->getGuarantor() !== null, [new Contract(new Guarantor(false))], true];
        yield 'relation missing' => ['`$contract->getGuarantor() !== null`', static fn (Contract $contract): bool => $contract->getGuarantor() !== null, [new Contract(null)], false];
        $companyGuarantor = static fn (Contract $contract): bool => $contract->getGuarantor()?->isCompany() === true;
        yield 'relation field true' => ['`$contract->getGuarantor()?->isCompany() === true`', $companyGuarantor, [new Contract(new Guarantor(true))], true];
        yield 'relation field on missing relation' => ['`$contract->getGuarantor()?->isCompany() === true`', $companyGuarantor, [new Contract(null)], false];
        yield 'collection empty' => ['`!$order->getLines()->isEmpty()`', static fn (Order $order): bool => !$order->getLines()->isEmpty(), [new Order()], false];
        yield 'collection non-empty' => ['`!$order->getLines()->isEmpty()`', static fn (Order $order): bool => !$order->getLines()->isEmpty(), [new Order([new OrderLine()])], true];
        yield 'collection size' => ['`$order->getLines()->count() >= 3`', static fn (Order $order): bool => $order->getLines()->count() >= 3, [new Order([new OrderLine(), new OrderLine(), new OrderLine()])], true];
        $hazardous = static fn (Order $order): bool => $order->getLines()->exists(static fn (int $key, OrderLine $line): bool => $line->isHazardous());
        yield 'collection exists' => ['`$order->getLines()->exists(static fn (int $key, OrderLine $line): bool => $line->isHazardous())`', $hazardous, [new Order([new OrderLine(), new OrderLine(true)])], true];
        yield 'collection exists none' => ['`$order->getLines()->exists(static fn (int $key, OrderLine $line): bool => $line->isHazardous())`', $hazardous, [new Order([new OrderLine()])], false];
        yield 'pitfall collection object truthy' => ['`(bool) $order->getLines()` is `true`', static fn (mixed $lines): bool => (bool) $lines, [(new Order())->getLines()], true];

        // Symfony request parameters
        $getBoolean = static fn (Request $request): bool => $request->query->getBoolean('annex');
        yield 'request getBoolean yes' => ["`\$request->query->getBoolean('annex')`", $getBoolean, [new Request(['annex' => 'yes'])], true];
        yield 'request getBoolean false string' => ["`\$request->query->getBoolean('annex')`", $getBoolean, [new Request(['annex' => 'false'])], false];
        yield 'request getBoolean off' => ["`\$request->query->getBoolean('annex')`", $getBoolean, [new Request(['annex' => 'off'])], false];
        yield 'request getBoolean missing' => ["`\$request->query->getBoolean('annex')`", $getBoolean, [new Request()], false];
        $copies = static fn (Request $request): bool => $request->query->getInt('copies') > 1;
        yield 'request getInt' => ["`\$request->query->getInt('copies') > 1`", $copies, [new Request(['copies' => '3'])], true];
        yield 'request getInt missing' => ["`\$request->query->getInt('copies') > 1`", $copies, [new Request()], false];
        $lenient        = static fn (Request $request): bool => filter_var($request->request->get('annex'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;
        $lenientSnippet = "`filter_var(\$request->request->get('annex'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true`";
        yield 'request lenient unknown' => [$lenientSnippet, $lenient, [new Request([], ['annex' => 'maybe'])], false];
        yield 'request lenient on' => [$lenientSnippet, $lenient, [new Request([], ['annex' => 'on'])], true];
        yield 'pitfall request bool cast' => ["`(bool) \$request->query->get('annex')`", static fn (Request $request): bool => (bool) $request->query->get('annex'), [new Request(['annex' => 'false'])], true];

        // Symfony form data (types produced by the form types listed in the docs)
        yield 'form checkbox' => ["`\$data['annex'] === true`", static fn (array $data): bool => $data['annex'] === true, [['annex' => false]], false];
        yield 'form integer null' => ["`(\$data['copies'] ?? 0) > 1`", static fn (array $data): bool => ($data['copies'] ?? 0) > 1, [['copies' => null]], false];
        yield 'form integer two' => ["`(\$data['copies'] ?? 0) > 1`", static fn (array $data): bool => ($data['copies'] ?? 0) > 1, [['copies' => 2]], true];
        yield 'form text null' => ["`trim(\$data['notes'] ?? '') !== ''`", static fn (array $data): bool => trim($data['notes'] ?? '') !== '', [['notes' => null]], false];
        yield 'form enum' => ["`\$data['status'] === InvoiceStatus::Paid`", static fn (array $data): bool => $data['status'] === InvoiceStatus::Paid, [['status' => InvoiceStatus::Paid]], true];
        yield 'form enum unselected' => ["`\$data['status'] === InvoiceStatus::Paid`", static fn (array $data): bool => $data['status'] === InvoiceStatus::Paid, [['status' => null]], false];
        yield 'form choice yes' => ["`\$data['newsletter'] === 'yes'`", static fn (array $data): bool => $data['newsletter'] === 'yes', [['newsletter' => 'no']], false];

        // combining conditions
        $euVat = static fn (bool $isCompany, string $country): bool => $isCompany && $country !== 'ES';
        yield 'and flattened' => ["`new ConditionalBlock('eu_vat_note', \$isCompany && \$country !== 'ES')`", $euVat, [true, 'PT'], true];
        yield 'and flattened domestic' => ["`new ConditionalBlock('eu_vat_note', \$isCompany && \$country !== 'ES')`", $euVat, [true, 'ES'], false];
        $priority = static fn (bool $isVip, int $total): bool => $isVip || $total >= 10_000;
        yield 'or flattened' => ["`new ConditionalBlock('priority', \$isVip || \$total >= 10_000)`", $priority, [false, 10_000], true];
        yield 'or flattened neither' => ["`new ConditionalBlock('priority', \$isVip || \$total >= 10_000)`", $priority, [false, 9_999], false];
    }

    public function testRequestGetBooleanRejectsUnknownValues(): void
    {
        self::assertStringContainsString('anything else (`maybe`) → `BadRequestException` (HTTP 400)', $this->usage());

        $this->expectException(BadRequestException::class);
        (new Request(['annex' => 'maybe']))->query->getBoolean('annex');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIntegers(): iterable
    {
        yield 'letters' => ['abc'];
        yield 'empty' => [''];
        yield 'decimal' => ['3.5'];
    }

    #[DataProvider('invalidIntegers')]
    public function testRequestGetIntRejectsNonIntegers(string $raw): void
    {
        self::assertStringContainsString('a non-integer (`abc`, `\'\'`, `3.5`) → `BadRequestException` (HTTP 400)', $this->usage());

        $this->expectException(BadRequestException::class);
        (new Request(['copies' => $raw]))->query->getInt('copies');
    }

    public function testEnumFromThrowsForUnknownValue(): void
    {
        self::assertStringContainsString("`InvoiceStatus::from('unknown')` throws a `ValueError`", $this->usage());

        $this->expectException(ValueError::class);
        InvoiceStatus::from('unknown');
    }

    public function testStrictModeRejectsNonBoolVisibility(): void
    {
        self::assertStringContainsString('Argument #2 ($visible) must be of type bool, int given', $this->usage());

        try {
            $block = new ConditionalBlock('discount', $this->mixed(10));
            self::fail('TypeError expected, got visible=' . var_export($block->visible, true));
        } catch (TypeError $e) {
            self::assertStringContainsString('Argument #2 ($visible) must be of type bool, int given', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBoolValues(): iterable
    {
        yield 'int 1' => [1];
        yield 'int 0' => [0];
        yield 'float 0.0' => [0.0];
        yield 'string yes' => ['yes'];
        yield 'string 0' => ['0'];
        yield 'null' => [null];
        yield 'array' => [[]];
    }

    #[DataProvider('nonBoolValues')]
    public function testStrictModeThrowsTypeErrorForEveryNonBool(mixed $value): void
    {
        $this->expectException(TypeError::class);
        $block = new ConditionalBlock('block', $value);
        self::fail('TypeError expected, got visible=' . var_export($block->visible, true));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function weakModeCoercions(): iterable
    {
        yield 'int 1' => ['1', true];
        yield 'int 0' => ['0', false];
        yield 'float 0.0' => ['0.0', false];
        yield 'empty string' => ["''", false];
        yield 'string 0' => ["'0'", false];
        yield 'string false' => ["'false'", true];
        yield 'string no' => ["'no'", true];
        yield 'string off' => ["'off'", true];
    }

    /**
     * eval()'d code has no declare(strict_types=1), so it shows what a weak-mode caller gets.
     */
    #[DataProvider('weakModeCoercions')]
    public function testWeakModeCoercesScalars(string $phpLiteral, bool $expected): void
    {
        $block = eval('return new \\' . ConditionalBlock::class . "('block', {$phpLiteral});");

        self::assertInstanceOf(ConditionalBlock::class, $block);
        self::assertSame($expected, $block->visible);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function weakModeTypeErrors(): iterable
    {
        yield 'null' => ['null'];
        yield 'array' => ['[]'];
    }

    #[DataProvider('weakModeTypeErrors')]
    public function testWeakModeStillRejectsNullAndArrays(string $phpLiteral): void
    {
        $this->expectException(TypeError::class);
        eval('return new \\' . ConditionalBlock::class . "('block', {$phpLiteral});");
    }

    public function testEntityInContextIsRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);
        ContextFlattener::flatten(['contract' => new Contract(null)]);
    }

    /**
     * @return iterable<string, array{list<array{code: string, description: string}>, list<string>, list<string>}>
     */
    public static function tableLines(): iterable
    {
        yield 'with rows' => [
            [['code' => 'A-1', 'description' => 'Anchor bolts'], ['code' => 'B-2', 'description' => 'Brackets']],
            ['Code', 'A-1', 'Anchor bolts', 'B-2', 'Brackets', 'Footer'],
            ['No items in this order.', '#if', '#endif', '${line_code}'],
        ];
        yield 'without rows' => [
            [],
            ['No items in this order.', 'Footer'],
            ['Code', '#if', '#endif', '${line_code}'],
        ];
    }

    /**
     * @param list<array{code: string, description: string}> $lines
     * @param list<string> $present
     * @param list<string> $absent
     */
    #[DataProvider('tableLines')]
    public function testTableOrNoItemsPattern(array $lines, array $present, array $absent): void
    {
        $templatePath = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if has_lines}');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('Code');
            $table->addCell(4000)->addText('Description');
            $table->addRow();
            $table->addCell(2000)->addText('${line_code}');
            $table->addCell(4000)->addText('${line_desc}');
            $section->addText('${#endif has_lines}');
            $section->addText('${#if no_lines}');
            $section->addText('No items in this order.');
            $section->addText('${#endif no_lines}');
            $section->addText('Footer');
        });
        $processor = new WordTemplateProcessor();

        // --- docs/USAGE.md "Show a table (or a "no items" message) depending on its rows" ---
        $hasLines = $lines !== [];

        $context = [
            'has_lines' => new ConditionalBlock('has_lines', $hasLines),
            'no_lines'  => new ConditionalBlock('no_lines', !$hasLines),
        ];

        if ($hasLines) {
            $context['lines'] = new TableRows('line_code', array_map(
                static fn (array $line): array => ['line_code' => $line['code'], 'line_desc' => $line['description']],
                $lines,
            ));
        }

        $result = $processor->process($templatePath, $context);
        // --- end of snippet ---

        $this->assertDocumentText($result->path(), $present, $absent, $templatePath);
        $result->dispose();
    }

    public function testEmptyTableRowsIsRejected(): void
    {
        self::assertStringContainsString('`TableRows` needs **at least one** row (`InvalidContextValueException` otherwise)', $this->usage());

        $this->expectException(InvalidContextValueException::class);
        $this->renderWith($this->tableTemplate(), [
            'has_lines' => new ConditionalBlock('has_lines', true),
            'lines'     => new TableRows('line_code', []),
        ]);
    }

    public function testTableRowsInsideHiddenBlockThrows(): void
    {
        self::assertStringContainsString("PHPWord's `Can not clone row, template variable not found or variable contains markup.`", $this->usage());

        $this->expectException(PhpWordException::class);
        $this->expectExceptionMessage('Can not clone row, template variable not found or variable contains markup.');
        $this->renderWith($this->tableTemplate(), [
            'has_lines' => new ConditionalBlock('has_lines', false),
            'lines'     => new TableRows('line_code', [['line_code' => 'A-1', 'line_desc' => 'Anchor bolts']]),
        ]);
    }

    public function testValuesForPlaceholdersInsideHiddenBlockAreIgnored(): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if annex}');
            $section->addText('Annex ${annex_title}');
            $section->addText('${annex_body}');
            $section->addText('${#endif annex}');
            $section->addText('Footer');
        }, [
            'annex'       => new ConditionalBlock('annex', false),
            'annex_title' => 'Hidden title',
            'annex_body'  => new HtmlContent('<p>Hidden body</p>'),
        ]);

        self::assertSame('Footer', $text);
    }

    /**
     * @return iterable<string, array{InvoiceStatus, string, string}>
     */
    public static function invoiceStatuses(): iterable
    {
        yield 'paid' => [InvoiceStatus::Paid, 'Thank you, invoice F-42 is paid.', 'Please pay'];
        yield 'draft' => [InvoiceStatus::Draft, 'Please pay invoice F-42 before 15/11/2026.', 'Thank you'];
        yield 'overdue' => [InvoiceStatus::Overdue, 'Please pay invoice F-42 before 15/11/2026.', 'Thank you'];
    }

    #[DataProvider('invoiceStatuses')]
    public function testElsePattern(InvoiceStatus $status, string $present, string $absent): void
    {
        $invoice      = new Invoice('F-42', $status, new DateTimeImmutable('2026-11-15'));
        $templatePath = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if paid}');
            $section->addText('Thank you, invoice ${number} is paid.');
            $section->addText('${#endif paid}');
            $section->addText('${#if not_paid}');
            $section->addText('Please pay invoice ${number} before ${due_date}.');
            $section->addText('${#endif not_paid}');
        });

        // --- docs/USAGE.md "else": two blocks with opposite conditions ---
        $isPaid = $invoice->status === InvoiceStatus::Paid;

        $context = [
            'number'   => $invoice->number,
            'due_date' => $invoice->dueDate->format('d/m/Y'),
            'paid'     => new ConditionalBlock('paid', $isPaid),
            'not_paid' => new ConditionalBlock('not_paid', !$isPaid),
        ];
        // --- end of snippet ---

        $result = (new WordTemplateProcessor())->process($templatePath, $context);
        $this->assertDocumentText($result->path(), [$present], [$absent, '#if', '#endif'], $templatePath);
        $result->dispose();
    }

    /**
     * @return iterable<string, array{InvoiceStatus, string, string}>
     */
    public static function oneOfNStatuses(): iterable
    {
        yield 'draft' => [InvoiceStatus::Draft, 'Draft — not valid as an invoice.', 'Tone: reminder'];
        yield 'paid' => [InvoiceStatus::Paid, 'Paid — thank you.', 'Tone: thanks'];
        yield 'overdue' => [InvoiceStatus::Overdue, 'Overdue — please pay now.', 'Tone: reminder'];
    }

    #[DataProvider('oneOfNStatuses')]
    public function testOneOfNFromEnumAndMatch(InvoiceStatus $status, string $statusText, string $toneText): void
    {
        $invoice      = new Invoice('F-7', $status, new DateTimeImmutable('2026-11-15'));
        $templatePath = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            foreach (['draft' => 'Draft — not valid as an invoice.', 'paid' => 'Paid — thank you.', 'overdue' => 'Overdue — please pay now.'] as $name => $label) {
                $section->addText('${#if status_' . $name . '}');
                $section->addText($label);
                $section->addText('${#endif status_' . $name . '}');
            }
            foreach (['thanks', 'reminder'] as $tone) {
                $section->addText('${#if tone_' . $tone . '}');
                $section->addText('Tone: ' . $tone);
                $section->addText('${#endif tone_' . $tone . '}');
            }
        });
        $context = [];

        // --- docs/USAGE.md "One of N (switch)" — from a backed enum ---
        $context['status_blocks'] = array_map(
            static fn (InvoiceStatus $case): ConditionalBlock => new ConditionalBlock('status_' . $case->value, $invoice->status === $case),
            InvoiceStatus::cases(),
        );

        // --- several cases share one text ---
        $tone = match ($invoice->status) {
            InvoiceStatus::Paid                          => 'thanks',
            InvoiceStatus::Draft, InvoiceStatus::Overdue => 'reminder',
        };

        $context['tone_blocks'] = [
            new ConditionalBlock('tone_thanks', $tone === 'thanks'),
            new ConditionalBlock('tone_reminder', $tone === 'reminder'),
        ];
        // --- end of snippet ---

        $all    = ['Draft — not valid as an invoice.', 'Paid — thank you.', 'Overdue — please pay now.', 'Tone: thanks', 'Tone: reminder'];
        $result = (new WordTemplateProcessor())->process($templatePath, $context);
        $this->assertDocumentText($result->path(), [$statusText, $toneText], [...array_values(array_diff($all, [$statusText, $toneText])), '#if', '#endif'], $templatePath);
        $result->dispose();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plans(): iterable
    {
        yield 'pro with noise' => ['  PRO ', 'Plan: pro'];
        yield 'basic' => ['basic', 'Plan: basic'];
        yield 'unexpected value' => ['gold', 'Plan: other'];
    }

    #[DataProvider('plans')]
    public function testOneOfNFromStringWithFallback(string $rawPlan, string $expected): void
    {
        $customer     = new Customer($rawPlan);
        $templatePath = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            foreach (['basic', 'pro', 'enterprise', 'other'] as $name) {
                $section->addText('${#if plan_' . $name . '}');
                $section->addText('Plan: ' . $name);
                $section->addText('${#endif plan_' . $name . '}');
            }
        });
        $context = [];

        // --- docs/USAGE.md "One of N (switch)" — from a free string ---
        $plans = ['basic', 'pro', 'enterprise'];
        $plan  = strtolower(trim($customer->plan));

        $context['plan_blocks'] = array_map(
            static fn (string $option): ConditionalBlock => new ConditionalBlock('plan_' . $option, $plan === $option),
            $plans,
        );
        $context['plan_other'] = new ConditionalBlock('plan_other', !in_array($plan, $plans, true));
        // --- end of snippet ---

        $text = $this->renderWith($templatePath, $context);
        self::assertSame($expected, $text);
    }

    /**
     * @return iterable<string, array{bool, bool, string}>
     */
    public static function nestedAnd(): iterable
    {
        yield 'both' => [true, true, 'Company details: ACME Intra-community VAT number: PT000000000'];
        yield 'outer only' => [true, false, 'Company details: ACME'];
        yield 'inner only' => [false, true, ''];
        yield 'none' => [false, false, ''];
    }

    #[DataProvider('nestedAnd')]
    public function testNestingIsAnAnd(bool $company, bool $foreign, string $expected): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if company}');
            $section->addText('Company details: ${company_name}');
            $section->addText('${#if foreign_company}');
            $section->addText('Intra-community VAT number: ${vat_number}');
            $section->addText('${#endif foreign_company}');
            $section->addText('${#endif company}');
        }, [
            'company'         => new ConditionalBlock('company', $company),
            'foreign_company' => new ConditionalBlock('foreign_company', $foreign),
            'company_name'    => 'ACME',
            'vat_number'      => 'PT000000000',
        ]);

        self::assertSame($expected, $text);
    }

    public function testSameNameInHeaderAndBodyIsToggledTogether(): void
    {
        $build = static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $header  = $section->addHeader();
            $header->addText('${#if draft}');
            $header->addText('DRAFT header');
            $header->addText('${#endif draft}');
            $header->addText('Company header');
            $section->addText('Body');
            $section->addText('${#if draft}');
            $section->addText('DRAFT body');
            $section->addText('${#endif draft}');
            $footer = $section->addFooter();
            $footer->addText('${#if draft}');
            $footer->addText('DRAFT footer');
            $footer->addText('${#endif draft}');
            $footer->addText('Page footer');
        };

        foreach ([true, false] as $isDraft) {
            $templatePath = $this->createTemplate($build);
            $result       = (new WordTemplateProcessor())->process($templatePath, ['draft' => new ConditionalBlock('draft', $isDraft)]);

            try {
                $header = $this->partText($result->path(), 'word/header1.xml');
                $body   = $this->partText($result->path(), 'word/document.xml');
                $footer = $this->partText($result->path(), 'word/footer1.xml');
                self::assertSame($isDraft ? 'DRAFT header Company header' : 'Company header', $header);
                self::assertSame($isDraft ? 'Body DRAFT body' : 'Body', $body);
                self::assertSame($isDraft ? 'DRAFT footer Page footer' : 'Page footer', $footer);
            } finally {
                $result->dispose();
                @unlink($templatePath);
            }
        }
    }

    public function testSameNameTwiceInBodyIsToggledTogether(): void
    {
        $build = static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if a}');
            $section->addText('First');
            $section->addText('${#endif a}');
            $section->addText('Middle');
            $section->addText('${#if a}');
            $section->addText('Second');
            $section->addText('${#endif a}');
        };

        self::assertSame('First Middle Second', $this->renderWith($build, ['a' => new ConditionalBlock('a', true)]));
        self::assertSame('Middle', $this->renderWith($build, ['a' => new ConditionalBlock('a', false)]));
    }

    public function testDuplicateBlockNameInContextLastOneWins(): void
    {
        $build = $this->simpleBlock('annex', 'Annex text');

        self::assertSame('Before After', $this->renderWith($build, ['first' => new ConditionalBlock('annex', true), 'second' => new ConditionalBlock('annex', false)]));
        self::assertSame('Before Annex text After', $this->renderWith($build, ['first' => new ConditionalBlock('annex', false), 'second' => new ConditionalBlock('annex', true)]));
    }

    public function testSameNameNestedInsideItselfLeavesMarkers(): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if a}');
            $section->addText('Outer');
            $section->addText('${#if a}');
            $section->addText('Inner');
            $section->addText('${#endif a}');
            $section->addText('${#endif a}');
            $section->addText('After');
        }, ['a' => new ConditionalBlock('a', false)]);

        self::assertStringContainsString('${#if a}', $text);
    }

    public function testMissingConditionalBlockLeavesMarkersAsLiteralText(): void
    {
        $text = $this->renderWith($this->simpleBlock('annex', 'Annex ${annex_title}'), [
            'annex_title' => 'Terms',
            'other'       => new ConditionalBlock('not_in_template', false),
        ]);

        self::assertSame('Before ${#if annex} Annex Terms ${#endif annex} After', $text);
    }

    public function testUnmatchedEndifLeavesMarkersAsLiteralText(): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if annex}');
            $section->addText('Annex');
            $section->addText('${#endif anex}');
            $section->addText('After');
        }, ['annex' => new ConditionalBlock('annex', false)]);

        self::assertSame('${#if annex} Annex ${#endif anex} After', $text);
    }

    public function testBlockNamesMatchCaseInsensitivelyButAreListedAsTyped(): void
    {
        $build = $this->simpleBlock('Annex', 'Annex text');

        self::assertSame('Before After', $this->renderWith($build, ['annex' => new ConditionalBlock('annex', false)]));

        $templatePath = $this->createTemplate($build);
        try {
            self::assertSame(['Annex'], (new WordTemplateProcessor())->listConditionalBlocks($templatePath));
        } finally {
            @unlink($templatePath);
        }
    }

    public function testDottedBlockNameIsIndependentOfContextKey(): void
    {
        $text = $this->renderWith($this->simpleBlock('invoice.paid', 'Paid'), [
            'whatever' => ['nested' => new ConditionalBlock('invoice.paid', false)],
        ]);

        self::assertSame('Before After', $text);
    }

    public function testFailFastSnippetReportsMissingBlocks(): void
    {
        $templatePath = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            foreach (['annex', 'vip_section', 'draft'] as $name) {
                $section->addText('${#if ' . $name . '}');
                $section->addText($name);
                $section->addText('${#endif ' . $name . '}');
            }
        });
        $processor = new WordTemplateProcessor();
        $hasAnnex  = true;
        $isVip     = false;
        $values    = [];

        try {
            // --- docs/USAGE.md "What happens when…" fail-fast snippet ---
            $blocks = [
                new ConditionalBlock('annex', $hasAnnex),
                new ConditionalBlock('vip_section', $isVip),
            ];

            $supplied = array_map(static fn (ConditionalBlock $block): string => $block->blockName, $blocks);
            $missing  = array_diff($processor->listConditionalBlocks($templatePath), $supplied);

            if ($missing !== []) {
                throw new LogicException('No visibility decided for block(s): ' . implode(', ', $missing));
            }

            $result = $processor->process($templatePath, ['blocks' => $blocks] + $values);
            // --- end of snippet ---
            $result->dispose();
            self::fail('LogicException expected');
        } catch (LogicException $e) {
            self::assertSame('No visibility decided for block(s): draft', $e->getMessage());
        } finally {
            @unlink($templatePath);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function visibility(): iterable
    {
        yield 'visible' => [true];
        yield 'hidden' => [false];
    }

    #[DataProvider('visibility')]
    public function testTextBeforeMarkerIsRemovedWithMarkerParagraph(bool $visible): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('Before');
            $section->addText('Notes: ${#if notes}');
            $section->addText('Body');
            $section->addText('${#endif notes}');
            $section->addText('After');
        }, ['notes' => new ConditionalBlock('notes', $visible)]);

        self::assertSame($visible ? 'Before Body After' : 'Before After', $text);
    }

    #[DataProvider('visibility')]
    public function testTextAfterMarkerPreventsMatching(bool $visible): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if notes}');
            $section->addText('Body');
            $section->addText('${#endif notes} End.');
        }, ['notes' => new ConditionalBlock('notes', $visible)]);

        self::assertSame('${#if notes} Body ${#endif notes} End.', $text);
    }

    #[DataProvider('visibility')]
    public function testInlinePairIsNotSupported(bool $visible): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $pw->addSection()->addText('Hello ${#if vip}dear ${#endif vip}client');
        }, ['vip' => new ConditionalBlock('vip', $visible)]);

        self::assertSame('Hello ${#if vip}dear ${#endif vip}client', $text);
    }

    #[DataProvider('visibility')]
    public function testMarkersInsideOneTableCell(bool $visible): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $table   = $section->addTable();
            $table->addRow();
            $cell = $table->addCell(3000);
            $cell->addText('Cell head');
            $cell->addText('${#if extra}');
            $cell->addText('Cell extra');
            $cell->addText('${#endif extra}');
            $table->addCell(3000)->addText('Other cell');
            $section->addText('After');
        }, ['extra' => new ConditionalBlock('extra', $visible)]);

        self::assertSame($visible ? 'Cell head Cell extra Other cell After' : 'Cell head Other cell After', $text);
    }

    #[DataProvider('visibility')]
    public function testContentAcrossPageBreak(bool $visible): void
    {
        $text = $this->renderWith(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if appendix}');
            $section->addText('Page one');
            $section->addPageBreak();
            $section->addText('Page two');
            $section->addText('${#endif appendix}');
            $section->addText('After');
        }, ['appendix' => new ConditionalBlock('appendix', $visible)]);

        self::assertSame($visible ? 'Page one Page two After' : 'After', $text);
    }

    public function testFootnotesAreNotProcessed(): void
    {
        $templatePath = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $run     = $section->addTextRun();
            $run->addText('Body');
            $footnote = $run->addFootnote();
            $footnote->addText('${#if note}');
            $footnote->addText('${#endif note}');
        });
        $result = (new WordTemplateProcessor())->process($templatePath, ['note' => new ConditionalBlock('note', false)]);

        try {
            self::assertStringContainsString('${#if note}', $this->partText($result->path(), 'word/footnotes.xml'));
        } finally {
            $result->dispose();
            @unlink($templatePath);
        }
    }

    /**
     * Runs a template (builder or existing path) with the given context and returns the body text,
     * one space between paragraphs.
     *
     * @param array<string, mixed> $context
     */
    private function renderWith(callable|string $template, array $context): string
    {
        $templatePath = is_string($template) ? $template : $this->createTemplate($template);

        try {
            $result = (new WordTemplateProcessor())->process($templatePath, $context);
        } catch (Throwable $e) {
            @unlink($templatePath);

            throw $e;
        }

        try {
            return $this->partText($result->path(), 'word/document.xml');
        } finally {
            $result->dispose();
            @unlink($templatePath);
        }
    }

    /**
     * @param list<string> $present
     * @param list<string> $absent
     */
    private function assertDocumentText(string $docxPath, array $present, array $absent, string $templatePath): void
    {
        try {
            $text = $this->partText($docxPath, 'word/document.xml');
            foreach ($present as $needle) {
                self::assertStringContainsString($needle, $text);
            }
            foreach ($absent as $needle) {
                self::assertStringNotContainsString($needle, $text);
            }
        } finally {
            @unlink($templatePath);
        }
    }

    private function partText(string $docxPath, string $part): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName($part);
        $zip->close();
        self::assertIsString($xml, $part . ' missing');

        $text = strip_tags(str_replace('</w:p>', ' </w:p>', $xml));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1)));
    }

    private function simpleBlock(string $name, string $content): Closure
    {
        return static function (PhpWord $pw) use ($name, $content): void {
            $section = $pw->addSection();
            $section->addText('Before');
            $section->addText('${#if ' . $name . '}');
            $section->addText($content);
            $section->addText('${#endif ' . $name . '}');
            $section->addText('After');
        };
    }

    private function tableTemplate(): Closure
    {
        return static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if has_lines}');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('${line_code}');
            $table->addCell(4000)->addText('${line_desc}');
            $section->addText('${#endif has_lines}');
        };
    }

    private function createTemplate(callable $build): string
    {
        $path = sys_get_temp_dir() . '/nowo_word_cond_' . bin2hex(random_bytes(8)) . '.docx';
        $pw   = new PhpWord();
        $build($pw);
        IOFactory::createWriter($pw, 'Word2007')->save($path);

        return $path;
    }

    private function usage(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/USAGE.md');
        self::assertIsString($contents);

        return $contents;
    }

    /**
     * Hides a literal from static analysis so the strict-mode TypeError can be exercised.
     */
    private function mixed(mixed $value): mixed
    {
        return $value;
    }
}
