<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use RuntimeException;

final class ProcessingTimedOutException extends RuntimeException implements WordTemplateExceptionInterface
{
}
