<?php

namespace Webman\Resolvers\Parameters;

interface ParameterResolverInterface
{
    public function resolve(array &$parameters, mixed $parameterValue, string $parameterName, string $typeName, ?bool $debug = false): void;
}
