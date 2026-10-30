<?php

declare(strict_types=1);

namespace App\Command;

use App\Conditionals\ExpressionExampleRunner;
use App\Conditionals\ExpressionExamples;
use DateTimeImmutable;
use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function file_put_contents;
use function is_dir;
use function mkdir;

/**
 * Writes the template and the generated document of every /conditionals example to a directory
 * (used by "make docx-previews" to render the side-by-side images of docs/images/demo/).
 */
#[AsCommand(name: 'app:conditionals:export-examples', description: 'Export the template and generated .docx of every expression example.')]
final class ExportExpressionExamplesCommand
{
    public function __construct(
        private readonly ExpressionExampleRunner $runner,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Target directory')] string $directory): int
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        foreach (ExpressionExamples::all(DateTimeImmutable::createFromInterface($this->clock->now())) as $example) {
            file_put_contents($directory . '/' . $example['id'] . '-template.docx', $this->runner->templateBytes($example));

            try {
                file_put_contents($directory . '/' . $example['id'] . '-result.docx', $this->runner->resultBytes($example));
            } catch (ConditionalExpressionException $e) {
                file_put_contents($directory . '/' . $example['id'] . '-error.txt', $e->getMessage());
            }

            $io->writeln('Exported ' . $example['id']);
        }

        return Command::SUCCESS;
    }
}
