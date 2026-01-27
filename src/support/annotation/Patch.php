<?php

namespace support\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Patch extends \Webman\Annotation\Patch
{
}

