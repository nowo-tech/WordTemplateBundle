<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\WordTemplateFormType;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function array_diff;
use function array_filter;
use function array_unique;
use function array_values;
use function in_array;
use function pathinfo;
use function preg_match;
use function sort;

use const PATHINFO_FILENAME;

/**
 * Discovers placeholders in {@see public/demo/doc-final-tpl.docx} via {@see TemplateProcessor::getVariables()}
 * and renders a dynamic form so any user can fill them in and download the resulting `.docx`.
 *
 * - Variable names containing "." (e.g. {@code chapter.number}) are kept flat — the
 *   {@see \Nowo\WordTemplateBundle\Util\ContextFlattener} accepts them as-is.
 * - Variables in {@see ROW_GROUPS} are treated as repeating-row anchors (PHPWord
 *   {@code cloneRow}) and rendered as multi-row sections.
 * - Variables matched by {@see HTML_FIELD_PATTERNS} are wrapped in {@see HtmlContent}
 *   so the user can paste rich HTML.
 */
final class WordTemplateDemoController extends AbstractController
{
    /**
     * Filename of the .docx kept in {@code public/demo/}. Edit the file to change the form.
     */
    private const TEMPLATE_FILENAME = 'doc-final-tpl.docx';

    /**
     * Anchor variable => list of cell variables in the same table row to clone with PHPWord.
     *
     * @var array<string, list<string>>
     */
    private const ROW_GROUPS = [
        'row_code' => ['row_code', 'row_desc', 'row_value'],
        'ref_text' => ['ref_text'],
    ];

    /**
     * Variable-name regexps whose values are wrapped in {@see HtmlContent} (rich HTML block).
     *
     * @var list<string>
     */
    private const HTML_FIELD_PATTERNS = [
        '/^abstract$/',
        '/\.body$/',
        '/\.background$/',
        '/\.research_question$/',
        '/\.quote$/',
    ];

    #[Route('/', name: 'demo_home', methods: ['GET', 'POST'])]
    public function home(Request $request, WordTemplateProcessorInterface $processor): Response
    {
        $projectDir   = $this->getParameter('kernel.project_dir');
        $templatePath = $projectDir . '/public/demo/' . self::TEMPLATE_FILENAME;

        $variables = (new TemplateProcessor($templatePath))->getVariables();
        $variables = array_values(array_unique($variables));
        sort($variables);

        $rowVars    = $this->collectRowVariables();
        $simpleVars = array_values(array_diff($variables, $rowVars));
        $htmlVars   = array_values(array_filter($simpleVars, fn (string $v): bool => $this->isHtmlField($v)));

        $form = $this->createForm(WordTemplateFormType::class, null, [
            'simple_vars'  => $simpleVars,
            'html_vars'    => $htmlVars,
            'row_groups'   => self::ROW_GROUPS,
            'defaults'     => $this->defaults(),
            'default_rows' => $this->defaultRows(),
            'action'       => $this->generateUrl('demo_home'),
            'method'       => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{placeholders?: array<string, string|null>, rows?: array<string, list<array<string, string|null>>>} $data */
            $data    = $form->getData();
            $context = $this->buildContextFromForm($data, $simpleVars);

            $doc = $processor->process($templatePath, $context);

            try {
                $bytes = $doc->readContents();
            } finally {
                $doc->dispose();
            }

            return new Response($bytes, Response::HTTP_OK, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="' . pathinfo(self::TEMPLATE_FILENAME, PATHINFO_FILENAME) . '-filled.docx"',
            ]);
        }

        return $this->render('demo/index.html.twig', [
            'template_filename' => self::TEMPLATE_FILENAME,
            'all_variables'     => $variables,
            'simple_vars'       => $simpleVars,
            'html_vars'         => $htmlVars,
            'row_groups'        => self::ROW_GROUPS,
            'form'              => $form,
        ]);
    }

    /**
     * @param array{placeholders?: array<string, string|null>, rows?: array<string, list<array<string, string|null>>>} $data
     * @param list<string> $simpleVars
     *
     * @return array<string, HtmlContent|string|TableRows>
     */
    private function buildContextFromForm(array $data, array $simpleVars): array
    {
        $context = [];

        $placeholders = $data['placeholders'] ?? [];
        foreach ($simpleVars as $var) {
            $field = WordTemplateFormType::sanitize($var);
            $value = (string) ($placeholders[$field] ?? '');
            if ($this->isHtmlField($var) && $value !== '') {
                $context[$var] = new HtmlContent($value);

                continue;
            }
            $context[$var] = $value;
        }

        $rowsRaw = $data['rows'] ?? [];
        foreach (self::ROW_GROUPS as $anchor => $cells) {
            $rows      = [];
            $sanAnchor = WordTemplateFormType::sanitize($anchor);
            $rawRows   = $rowsRaw[$sanAnchor] ?? [];
            foreach ($rawRows as $rawRow) {
                $hasContent = false;
                $row        = [];
                foreach ($cells as $cell) {
                    $cellValue  = (string) ($rawRow[WordTemplateFormType::sanitize($cell)] ?? '');
                    $row[$cell] = $cellValue;
                    $hasContent = $hasContent || $cellValue !== '';
                }
                if ($hasContent) {
                    $rows[] = $row;
                }
            }

            if ($rows === []) {
                // TableRows requires ≥1 row; clear placeholders so the output has no leftover ${var}.
                foreach ($cells as $cell) {
                    $context[$cell] = '';
                }

                continue;
            }

            $context[$anchor] = new TableRows($anchor, $rows);
        }

        return $context;
    }

    private function isHtmlField(string $variable): bool
    {
        foreach (self::HTML_FIELD_PATTERNS as $pattern) {
            if (preg_match($pattern, $variable) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function collectRowVariables(): array
    {
        $vars = [];
        foreach (self::ROW_GROUPS as $cells) {
            foreach ($cells as $cell) {
                if (!in_array($cell, $vars, true)) {
                    $vars[] = $cell;
                }
            }
        }

        return $vars;
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'chapter.number'      => 'XII',
            'chapter.title'       => 'Sample chapter on demo data',
            'author1.name'        => 'Jane Doe',
            'author1.affiliation' => 'University of Demo',
            'author2.name'        => 'John Roe',
            'author2.affiliation' => 'Nowo.tech Research Group',
            'keywords'            => 'word, template, symfony, demo',
            'abstract'            => '<p>This chapter explores <strong>HTML-rich placeholders</strong> backed by'
                . ' PHPWord <code>Html::addHtml</code>: <em>inline formatting</em>, lists, and tables — all'
                . ' rendered into the final <code>.docx</code>.</p>',
            'introduction.body' => '<p>The introduction supports <strong>bold</strong>,'
                . ' <em>italic</em> and inline elements. Paragraphs are separated with paragraph tags.</p>'
                . '<p>You can also have <strong>multiple paragraphs</strong> in the same field.</p>',
            'introduction.background'        => '<p>Background paragraph with a <em>link-like</em> reference (Doe, 2026).</p>',
            'introduction.research_question' => '<p><strong>RQ:</strong> Can a Symfony bundle drive a Word template'
                . ' from arbitrary HTML reliably?</p>',
            'objectives.body' => '<p>This study has three main objectives:</p>'
                . '<p>1. Show how <strong>numbered items</strong> render in HtmlContent as paragraphs.</p>'
                . '<p>2. Demonstrate how nested context keys flatten with dots.</p>'
                . '<p>3. Validate the output against PHPWord setComplexBlock.</p>',
            'methodology.body' => '<p>The methodology relies on:</p>'
                . '<p>&bull; A <strong>bulleted</strong> series of paragraphs (real bullet/number lists need extra numbering setup in PHPWord).</p>'
                . '<p>&bull; An inline HTML <em>table</em> below.</p>'
                . '<p>&bull; Plain paragraphs around them.</p>'
                . '<p>HTML tables are fully supported:</p>'
                . '<table>'
                . '<tr><td><strong>Phase</strong></td><td><strong>Tool</strong></td></tr>'
                . '<tr><td>Parse</td><td>masterminds/html5</td></tr>'
                . '<tr><td>Render</td><td>PhpOffice/PhpWord</td></tr>'
                . '</table>',
            'methodology.quote' => '<p><em>"Templates should be boring; data should be interesting."</em></p>',
            'results.body'      => '<p>Aggregated metrics:</p>'
                . '<table>'
                . '<tr><td><strong>Metric</strong></td><td><strong>Value</strong></td></tr>'
                . '<tr><td>Templates filled</td><td>1.2k</td></tr>'
                . '<tr><td>Avg. time</td><td>~80&nbsp;ms</td></tr>'
                . '</table>',
            'discussion.body'  => '<p>Results align with the hypothesis: rich HTML inserts cleanly into <code>.docx</code> via the bundle.</p>',
            'conclusions.body' => '<p>Conclusions:</p>'
                . '<p>1. HTML <strong>tables</strong> and inline formatting work out of the box.</p>'
                . '<p>2. Nested keys remove form boilerplate.</p>'
                . '<p>3. <code>TableRows</code> covers true repeating rows in the template.</p>',
            'acknowledgements.body' => '<p>Funded by <strong>Nowo.tech</strong>.</p>',
            'figure1.caption'       => 'Aggregated demo metrics over 2026.',
            'figure1.source'        => 'Own elaboration',
            'table1.caption'        => 'Sample dataset.',
            'table1.source'         => 'Own elaboration',
        ];
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function defaultRows(): array
    {
        return [
            'row_code' => [
                ['row_code' => 'A1', 'row_desc' => 'First metric',  'row_value' => '42'],
                ['row_code' => 'B2', 'row_desc' => 'Second metric', 'row_value' => '108'],
            ],
            'ref_text' => [
                ['ref_text' => 'Doe, J. (2026). The art of demo data. Nowo Press.'],
                ['ref_text' => 'Roe, J. (2025). Templates and reproducibility. JOSS, 10(3).'],
            ],
        ];
    }
}
