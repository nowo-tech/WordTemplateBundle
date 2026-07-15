<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Processor;

use Nowo\WordTemplateBundle\Processor\TemplateProcessorBridge;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TemplateProcessorBridgeTest extends TestCase
{
    public function testTransformDocumentPartsUpdatesMainHeaderAndFooter(): void
    {
        $path = $this->createTemplate();

        try {
            $bridge = new TemplateProcessorBridge($path);
            $ref    = new ReflectionClass($bridge);

            $main = $ref->getProperty('tempDocumentMainPart');
            $main->setValue($bridge, '<main/>');

            $headers = $ref->getProperty('tempDocumentHeaders');
            $headers->setValue($bridge, [1 => '<header/>']);

            $footers = $ref->getProperty('tempDocumentFooters');
            $footers->setValue($bridge, [1 => '<footer/>']);

            $bridge->transformDocumentParts(static fn (string $xml): string => strtoupper($xml));

            self::assertSame('<MAIN/>', $main->getValue($bridge));
            self::assertSame([1 => '<HEADER/>'], $headers->getValue($bridge));
            self::assertSame([1 => '<FOOTER/>'], $footers->getValue($bridge));
        } finally {
            @unlink($path);
        }
    }

    private function createTemplate(): string
    {
        $path = sys_get_temp_dir() . '/nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
        $pw   = new PhpWord();
        $pw->addSection()->addText('x');
        IOFactory::createWriter($pw, 'Word2007')->save($path);

        return $path;
    }
}
