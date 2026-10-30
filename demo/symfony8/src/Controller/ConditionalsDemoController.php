<?php

declare(strict_types=1);

namespace App\Controller;

use App\Conditionals\ConditionalsContextBuilder;
use App\Form\ConditionalsFormType;
use App\Model\InvoiceStatus;
use DateTimeImmutable;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
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
 * "Conditionals by data type" demo: one form field per PHP type, each turned into
 * {@see ConditionalBlock}s with the expressions of docs/USAGE.md.
 * "Preview decisions" shows which blocks are visible; "Download .docx" merges
 * {@code public/demo/conditionals-tpl.docx} (built by {@code bin/build-conditionals-template.php}).
 */
final class ConditionalsDemoController extends AbstractController
{
    private const TEMPLATE_FILENAME = 'conditionals-tpl.docx';

    #[Route('/conditionals', name: 'demo_conditionals', methods: ['GET', 'POST'])]
    public function index(Request $request, WordTemplateProcessorInterface $processor, ConditionalsContextBuilder $builder): Response
    {
        $templatePath = $this->templatePath();
        $now          = new DateTimeImmutable();

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

        return $this->render('demo/conditionals.html.twig', [
            'form'              => $form,
            'decisions'         => $built['decisions'],
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

    private function templatePath(): string
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/demo/' . self::TEMPLATE_FILENAME;
        if (!is_file($path)) {
            throw $this->createNotFoundException(sprintf('Template "%s" not found — run bin/build-conditionals-template.php.', self::TEMPLATE_FILENAME));
        }

        return $path;
    }
}
