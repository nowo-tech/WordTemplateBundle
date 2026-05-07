<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use InvalidArgumentException;

final class TemplateNotFoundException extends InvalidArgumentException implements WordTemplateExceptionInterface
{
}
