<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use Nowo\WordTemplateBundle\Exception\PathNotAllowedException;

use function basename;
use function dirname;
use function is_dir;
use function is_string;
use function realpath;
use function sprintf;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * Ensures filesystem paths stay under configured roots (path traversal / SSRF-to-disk guard).
 */
final class PathAllowlist
{
    /**
     * @param list<string> $roots absolute directory roots; empty list skips the check (BC)
     * @param bool $mustExist When true, the path itself must exist (templates/images). When false, the parent directory must exist (output files).
     */
    public static function assertUnderRoots(string $path, array $roots, bool $mustExist = true): void
    {
        if ($roots === []) {
            return;
        }

        $resolved = self::resolve($path, $mustExist);
        foreach ($roots as $root) {
            if (!is_string($root) || $root === '') {
                continue;
            }
            $rootReal = realpath($root);
            if ($rootReal === false || !is_dir($rootReal)) {
                continue;
            }
            if ($resolved === $rootReal || str_starts_with($resolved, $rootReal . DIRECTORY_SEPARATOR)) {
                return;
            }
        }

        throw new PathNotAllowedException(sprintf('Path "%s" is outside nowo_word_template.allowed_roots.', $path));
    }

    private static function resolve(string $path, bool $mustExist): string
    {
        if ($mustExist) {
            $real = realpath($path);
            if ($real === false) {
                throw new PathNotAllowedException(sprintf('Path "%s" does not exist or is not readable.', $path));
            }

            return $real;
        }

        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }

        $parent = realpath(dirname($path));
        if ($parent === false) {
            throw new PathNotAllowedException(sprintf('Parent directory for path "%s" does not exist.', $path));
        }

        return $parent . DIRECTORY_SEPARATOR . basename($path);
    }
}
