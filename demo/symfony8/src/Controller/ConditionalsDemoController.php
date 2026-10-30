<?php

declare(strict_types=1);

namespace App\Controller;

use App\Conditionals\ConditionalsContextBuilder;
use App\Conditionals\ExpressionExampleRunner;
use App\Conditionals\ExpressionExamples;
use App\Form\ConditionalsFormType;
use App\Model\InvoiceStatus;
use DateTimeImmutable;
use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function file_get_contents;
use function is_file;
use function sprintf;

/**
 * "Conditionals" demo: one form field per PHP type, each turned into {@see ConditionalBlock}s with the
 * expressions of docs/USAGE.md, plus expression conditionals evaluated in the template (1.8).
 * "Preview decisions" shows which blocks are visible; "Download .docx" merges
 * {@code public/demo/conditionals-tpl.docx} (built by {@code bin/build-conditionals-template.php}).
 * Below the form, each {@see ExpressionExamples} entry shows the Word text, the PHP context and the result,
 * with the template and the generated document as downloads.
 */
final class ConditionalsDemoController extends AbstractController
{
    private const TEMPLATE_FILENAME = 'conditionals-tpl.docx';

    #[Route('/conditionals', name: 'demo_conditionals', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        WordTemplateProcessorInterface $processor,
        ConditionalsContextBuilder $builder,
        ConditionalExpressionEvaluator $evaluator,
        ClockInterface $clock,
    ): Response {
        $templatePath = $this->templatePath();
        $now          = DateTimeImmutable::createFromInterface($clock->now());

        $form = $this->createForm(ConditionalsFormType::class, [
            'customer_name'  => 'ACME Industries',
            'is_vip'         => true,
            'terms_accepted' => null,
            'quantity'       => 12,
            'discount'       => 0,
            'total'          => 149.9,
            'country'        => 'ES',
            'notes'          => '   ',
            'status'         => InvoiceStatus::Overdue,
            'due_date'       => $now->modify('-10 days')->setTime(0, 0),
            'lines'          => "A-1: Anchor bolts\nB-2: Brackets",
            'plan'           => '  PRO ',
        ], [
            'action' => $this->generateUrl('demo_conditionals'),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        /** @var array<string, mixed> $data */
        $data      = $form->getData() ?? [];
        $built     = $builder->build($data, $now);
        $submitted = $form->isSubmitted() && $form->isValid();

        if ($submitted) {
            $download = $form->get('download');
            if ($download instanceof ClickableInterface && $download->isClicked()) {
                $doc = $processor->process($templatePath, $built['context']);

                try {
                    return new Response($doc->readContents(), Response::HTTP_OK, [
                        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'Content-Disposition' => 'attachment; filename="conditionals-filled.docx"',
                    ]);
                } finally {
                    $doc->dispose();
                }
            }
        }

        $variables   = ExpressionVariables::fromContext($built['context']);
        $expressions = [];
        foreach (ConditionalsContextBuilder::EXPRESSIONS as $expression => $text) {
            try {
                $expressions[] = ['expression' => $expression, 'text' => $text, 'visible' => $evaluator->evaluate($expression, $variables), 'error' => null];
            } catch (ConditionalExpressionException $e) {
                $expressions[] = ['expression' => $expression, 'text' => $text, 'visible' => false, 'error' => $e->getMessage()];
            }
        }

        return $this->render('demo/conditionals.html.twig', [
            'form'              => $form,
            'decisions'         => $built['decisions'],
            'expressions'       => $expressions,
            'submitted'         => $submitted,
            'template_blocks'   => $processor->listConditionalBlocks($templatePath),
            'template_filename' => self::TEMPLATE_FILENAME,
        ]);
    }

    #[Route('/conditionals/template', name: 'demo_conditionals_template', methods: ['GET'])]
    public function downloadTemplate(): Response
    {
        $bytes = file_get_contents($this->templatePath());
        if ($bytes === false) {
            throw new RuntimeException(sprintf('Could not read template "%s".', self::TEMPLATE_FILENAME));
        }

        return new Response($bytes, Response::HTTP_OK, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . self::TEMPLATE_FILENAME . '"',
        ]);
    }

    #[Route('/conditionals/examples', name: 'demo_conditionals_examples', methods: ['GET'])]
    public function examples(ExpressionExampleRunner $runner, ClockInterface $clock): Response
    {
        $examples = [];
        foreach (ExpressionExamples::all(DateTimeImmutable::createFromInterface($clock->now())) as $example) {
            $examples[] = $example + $runner->preview($example);
        }

        return $this->render('demo/conditional_examples.html.twig', ['examples' => $examples]);
    }

    #[Route('/conditionals/examples/{id}/{kind}.docx', name: 'demo_conditionals_example', requirements: ['kind' => 'template|result'], methods: ['GET'])]
    public function downloadExample(string $id, string $kind, ExpressionExampleRunner $runner, ClockInterface $clock): Response
    {
        foreach (ExpressionExamples::all(DateTimeImmutable::createFromInterface($clock->now())) as $example) {
            if ($example['id'] !== $id) {
                continue;
            }

            try {
                $bytes = $kind === 'template' ? $runner->templateBytes($example) : $runner->resultBytes($example);
            } catch (ConditionalExpressionException $e) {
                return new Response($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            return new Response($bytes, Response::HTTP_OK, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="' . $id . '-' . $kind . '.docx"',
            ]);
        }

        throw $this->createNotFoundException(sprintf('Unknown example "%s".', $id));
    }

    private function templatePath(): string
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/demo/' . self::TEMPLATE_FILENAME;
        if (!is_file($path)) {
            throw $this->createNotFoundException(sprintf('Template "%s" not found — run bin/build-conditionals-template.php.', self::TEMPLATE_FILENAME));
        }

        return $path;
    }
}
