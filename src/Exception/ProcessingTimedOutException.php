<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use RuntimeException;

/**
 * Thrown when a merge exceeds the configured {@code nowo_word_template.timeout} (cooperative deadline).
 */
final class ProcessingTimedOutException extends RuntimeException implements WordTemplateExceptionInterface
{
}
