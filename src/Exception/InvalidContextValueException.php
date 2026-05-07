<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use InvalidArgumentException;

final class InvalidContextValueException extends InvalidArgumentException implements WordTemplateExceptionInterface
{
}
