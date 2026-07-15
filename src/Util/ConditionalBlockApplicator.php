<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use Nowo\WordTemplateBundle\Model\ConditionalBlock;

use function strlen;

use const PREG_OFFSET_CAPTURE;

/**
 * Shows or removes Twig-style conditional regions in WordprocessingML using PHPWord paragraph boundaries.
 * Nested blocks are resolved inside-out (deepest {@see ConditionalBlock} first).
 */
final readonly class ConditionalBlockApplicator
{
    public function __construct(
        private string $ifOpening,
        private string $ifClosing,
        private string $endifOpening,
        private string $endifClosing,
    ) {
    }

    public function openingMarker(string $blockName): string
    {
        return $this->ifOpening . ' ' . $blockName . $this->ifClosing;
    }

    public function closingMarker(string $blockName): string
    {
        return $this->endifOpening . ' ' . $blockName . $this->endifClosing;
    }

    /**
     * @param list<ConditionalBlock> $blocks
     */
    public function applyAll(string $xml, array $blocks): string
    {
        if ($blocks === []) {
            return $xml;
        }

        /** @var array<string, bool> $visibilityByName */
        $visibilityByName = [];
        foreach ($blocks as $block) {
            $visibilityByName[$block->blockName] = $block->visible;
        }

        while (true) {
            $regions = $this->findRegions($xml, array_keys($visibilityByName));
            if ($regions === []) {
                break;
            }

            $this->assignDepths($regions);

            $maxDepth = 0;
            foreach ($regions as $region) {
                if ($region['depth'] > $maxDepth) {
                    $maxDepth = $region['depth'];
                }
            }

            $deepest = array_values(array_filter(
                $regions,
                static fn (array $region): bool => $region['depth'] === $maxDepth,
            ));

            usort(
                $deepest,
                static fn (array $left, array $right): int => $right['fullStart'] <=> $left['fullStart'],
            );

            $applied = false;
            foreach ($deepest as $region) {
                $visible     = $visibilityByName[$region['blockName']];
                $replacement = $visible ? $region['content'] : '';
                $xml         = substr_replace($xml, $replacement, $region['fullStart'], $region['fullLength']);
                $applied     = true;
            }

            if (!$applied) {
                break;
            }
        }

        return $xml;
    }

    public function apply(string $xml, ConditionalBlock $block): string
    {
        return $this->applyAll($xml, [$block]);
    }

    /**
     * @param list<string> $blockNames
     *
     * @return list<array{blockName: string, fullStart: int, fullLength: int, content: string, depth: int}>
     */
    private function findRegions(string $xml, array $blockNames): array
    {
        $regions = [];

        foreach ($blockNames as $blockName) {
            $pattern = $this->buildPattern($blockName);
            $offset  = 0;

            while (preg_match($pattern, $xml, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $openStart  = $matches[2][1];
                $closeStart = $matches[4][1];
                $closeText  = $matches[4][0];
                $regions[]  = [
                    'blockName'  => $blockName,
                    'fullStart'  => $openStart,
                    'fullLength' => ($closeStart + strlen($closeText)) - $openStart,
                    'content'    => $matches[3][0],
                    'depth'      => 0,
                ];
                $offset = $closeStart + strlen($closeText);
            }
        }

        return $regions;
    }

    /**
     * @param list<array{blockName: string, fullStart: int, fullLength: int, content: string, depth: int}> $regions
     */
    private function assignDepths(array &$regions): void
    {
        foreach ($regions as &$region) {
            $regionEnd = $region['fullStart'] + $region['fullLength'];
            $depth     = 0;

            foreach ($regions as $other) {
                if ($other['fullStart'] < $region['fullStart'] && ($other['fullStart'] + $other['fullLength']) > $regionEnd) {
                    ++$depth;
                }
            }

            $region['depth'] = $depth;
        }
        unset($region);
    }

    private function buildPattern(string $blockName): string
    {
        $openMarker  = preg_quote($this->openingMarker($blockName), '/');
        $closeMarker = preg_quote($this->closingMarker($blockName), '/');

        return '/(.*((?s)<w:p\b(?:(?!<w:p\b).)*?' . $openMarker . '<\/w:.*?p>))(.*)((?s)<w:p\b(?:(?!<w:p\b).)[^$]*?' . $closeMarker . '<\/w:.*?p>)/is';
    }
}
