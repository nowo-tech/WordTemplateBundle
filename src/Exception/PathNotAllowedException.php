<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when a template, image or output path is outside {@code nowo_word_template.allowed_roots} or cannot be resolved.
 */
final class PathNotAllowedException extends InvalidArgumentException implements WordTemplateExceptionInterface
{
}
