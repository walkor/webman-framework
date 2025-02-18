<?php
declare(strict_types=1);

namespace Webman\Resolvers\Parameters;

use Support\exception\InputTypeException;

final class NumericParameterResolver implements ParameterResolverInterface
{

    public function resolve(
        array &$parameters,
        mixed $parameterValue,
        string $parameterName,
        string $typeName,
        ?bool $debug = false,
    ): void
    {
        if (!is_numeric($parameterValue)) {
            throw (new InputTypeException())->data([
                'parameter' => $parameterName,
                'exceptType' => $typeName,
                'actualType' => gettype($parameterValue),
            ])->debug($debug);
        }

        $parameters[$parameterName] = in_array($typeName, ['float', 'double']) ? (float)$parameterValue : (int)$parameterValue;
    }
}