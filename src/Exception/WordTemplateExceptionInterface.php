<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use Throwable;

/**
 * Marker implemented by every exception thrown by this bundle, so hosts can catch them with one type.
 */
interface WordTemplateExceptionInterface extends Throwable
{
}
