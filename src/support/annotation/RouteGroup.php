<?php

namespace support\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup extends \Webman\Annotation\RouteGroup
{
}

