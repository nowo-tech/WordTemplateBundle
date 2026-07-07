<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Processor;

use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WordTemplateProcessorTest extends TestCase
{
    public function testThrowsWhenPersistTemplateFails(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('Value=${v}');
        });

        $target = sys_get_temp_dir() . '/nowo_word_out_' . bin2hex(random_bytes(6)) . '.docx';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Simulated save failure');

            (new ThrowingWordTemplateProcessor())->process($tpl, ['v' => 'blocked'], $target);
        } finally {
            @unlink($tpl);
        }
    }

    /**
     * @param callable(PhpWord): void $build
     */
    private function createTemplate(callable $build): string
    {
        $path = sys_get_temp_dir() . '/nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
        $pw   = new PhpWord();
        $build($pw);
        IOFactory::createWriter($pw, 'Word2007')->save($path);

        return $path;
    }
}

readonly class ThrowingWordTemplateProcessor extends WordTemplateProcessor
{
    protected function persistTemplate(TemplateProcessor $processor, string $target): void
    {
        throw new RuntimeException('Simulated save failure');
    }
}
