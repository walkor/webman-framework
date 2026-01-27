<?php

namespace Webman\Annotation;

use Attribute;

/**
 * Disable webman's default route mapping for a controller or action.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DisableDefaultRoute
{
}