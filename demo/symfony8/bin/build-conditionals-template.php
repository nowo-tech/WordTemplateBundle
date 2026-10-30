<?php

declare(strict_types=1);

/*
 * Builds public/demo/conditionals-tpl.docx — the template of the "Conditionals by data type" demo page.
 * Every ${#if …} / ${#endif …} marker sits alone in its own paragraph (docs/USAGE.md "Where markers can go in Word").
 *
 * Usage (inside the demo container): php bin/build-conditionals-template.php
 */

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

require dirname(__DIR__) . '/vendor/autoload.php';

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);
$phpWord->addTitleStyle(1, ['size' => 18, 'bold' => true, 'color' => '1F3864']);
$phpWord->addTitleStyle(2, ['size' => 13, 'bold' => true, 'color' => '2C5282']);

$section = $phpWord->addSection();

/**
 * Adds "${#if name}", the given paragraphs and "${#endif name}" (one paragraph each).
 *
 * @param list<string> $paragraphs
 */
$block = static function (Section $section, string $name, array $paragraphs): void {
    $section->addText('${#if ' . $name . '}');
    foreach ($paragraphs as $paragraph) {
        $section->addText($paragraph);
    }
    $section->addText('${#endif ' . $name . '}');
};

$header = $section->addHeader();
$header->addText('${#if draft}');
$header->addText('DRAFT — not valid as an invoice', ['bold' => true, 'color' => 'C53030']);
$header->addText('${#endif draft}');
$header->addText('WordTemplateBundle — conditionals by data type', ['size' => 8, 'color' => '718096']);

$section->addTitle('Invoice for ${customer_name}', 1);
$section->addText('Each section below is kept or removed by a ConditionalBlock whose bool is decided in PHP (docs/USAGE.md, "Conditionals by data type").', ['italic' => true, 'color' => '4A5568']);

$section->addTitle('bool — VIP customer', 2);
$block($section, 'vip', ['VIP customer: priority support and free shipping are included.']);
$block($section, 'not_vip', ['Standard customer: support during office hours.']);

$section->addTitle('?bool — terms and conditions', 2);
$block($section, 'terms_accepted', ['The customer accepted the terms and conditions.']);
$block($section, 'terms_rejected', ['The customer rejected the terms and conditions — this invoice is informative only.']);
$block($section, 'terms_unknown', ['Terms and conditions are pending: please return the signed copy.']);

$section->addTitle('?int — quantity and discount', 2);
$block($section, 'has_quantity', ['Units ordered: ${quantity}.']);
$block($section, 'has_discount', ['Discount applied: ${discount} %.']);

$section->addTitle('?float — amount', 2);
$block($section, 'amount_due', ['Amount due: ${total} EUR.']);
$block($section, 'nothing_due', ['Nothing to pay.']);

$section->addTitle('string — tax note', 2);
$block($section, 'domestic', ['Domestic customer: Spanish VAT applies.']);
$block($section, 'foreign', ['Foreign customer: check the reverse-charge rules of the destination country.']);

$block($section, 'has_notes', ['Notes: ${notes}']);

$section->addTitle('Backed enum — invoice status (one of N)', 2);
$block($section, 'status_draft', ['Status: draft.']);
$block($section, 'status_paid', ['Status: paid — thank you!']);
$block($section, 'status_overdue', ['Status: overdue.']);

$block($section, 'overdue_notice', ['Payment was due on ${due_date}. Please pay as soon as possible.']);

$section->addTitle('array — order lines', 2);
$section->addText('${#if has_lines}');
$table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CBD5E0', 'cellMargin' => 80]);
$table->addRow();
$table->addCell(2000, ['bgColor' => '2C5282'])->addText('Code', ['bold' => true, 'color' => 'FFFFFF']);
$table->addCell(6000, ['bgColor' => '2C5282'])->addText('Description', ['bold' => true, 'color' => 'FFFFFF']);
$table->addRow();
$table->addCell(2000)->addText('${line_code}');
$table->addCell(6000)->addText('${line_desc}');
$section->addText('${#endif has_lines}');
$block($section, 'no_lines', ['No items in this order.']);

$section->addTitle('Free string — plan (one of N with fallback)', 2);
$block($section, 'plan_basic', ['Plan: Basic.']);
$block($section, 'plan_pro', ['Plan: Pro.']);
$block($section, 'plan_enterprise', ['Plan: Enterprise.']);
$block($section, 'plan_other', ['Plan: not recognised — please contact sales.']);

$target = dirname(__DIR__) . '/public/demo/conditionals-tpl.docx';
IOFactory::createWriter($phpWord, 'Word2007')->save($target);

echo 'Written ' . $target . \PHP_EOL;
