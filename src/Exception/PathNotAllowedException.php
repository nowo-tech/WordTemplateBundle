<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use InvalidArgumentException;

final class PathNotAllowedException extends InvalidArgumentException implements WordTemplateExceptionInterface
{
}
