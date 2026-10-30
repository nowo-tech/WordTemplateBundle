<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use function array_map;
use function count;
use function preg_match_all;
use function strlen;

use const PREG_OFFSET_CAPTURE;

/**
 * Offsets of the paragraph ({@code w:p}) and table row ({@code w:tr}) boundaries of one WordprocessingML part,
 * indexed once so that finding the element around a marker is a binary search (linear layout checks).
 *
 * @internal
 */
final readonly class BoundaryIndex
{
    /** @var array<string, array{open: list<int>, close: list<int>}> */
    private array $offsets;

    public function __construct(string $xml)
    {
        $offsets = [];
        foreach (['p', 'tr'] as $tag) {
            preg_match_all('#<w:' . $tag . '[ >]#', $xml, $open, PREG_OFFSET_CAPTURE);
            preg_match_all('#</w:' . $tag . '>#', $xml, $close, PREG_OFFSET_CAPTURE);
            $offsets[$tag] = [
                'open'  => array_map(static fn (array $m): int => $m[1], $open[0]),
                'close' => array_map(static fn (array $m): int => $m[1], $close[0]),
            ];
        }

        $this->offsets = $offsets;
    }

    /**
     * Bounds of the {@code <w:p>} / {@code <w:tr>} element holding [start, end), or null.
     *
     * @param 'p'|'tr' $tag
     *
     * @return array{int, int}|null
     */
    public function enclosing(int $start, int $end, string $tag): ?array
    {
        $opens  = $this->offsets[$tag]['open'];
        $closes = $this->offsets[$tag]['close'];

        // Last opening tag before $start.
        $i = $this->lowerBound($opens, $start) - 1;
        if ($i < 0) {
            return null;
        }
        $open = $opens[$i];

        // The element must still be open at $start: its first closing tag comes at or after $start.
        $firstClose = $this->lowerBound($closes, $open);
        if ($firstClose >= count($closes) || $closes[$firstClose] < $start) {
            return null;
        }

        $j = $this->lowerBound($closes, $end);
        if ($j >= count($closes)) {
            return null;
        }

        return [$open, $closes[$j] + strlen('</w:' . $tag . '>')];
    }

    /**
     * Index of the first element >= $value.
     *
     * @param list<int> $sorted
     */
    private function lowerBound(array $sorted, int $value): int
    {
        $low  = 0;
        $high = count($sorted);
        while ($low < $high) {
            $mid = ($low + $high) >> 1;
            if ($sorted[$mid] < $value) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low;
    }
}
