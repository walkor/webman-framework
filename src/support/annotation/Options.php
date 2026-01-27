<?php

namespace support\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Options extends \Webman\Annotation\Options
{
}

