<?php
declare(strict_types=1);

namespace Webman\Resolvers\Parameters;

use ReflectionEnum;
use Support\exception\InputValueException;
use UnitEnum;

final class EnumParameterResolver implements ParameterResolverInterface
{

    /**
     * @throws \ReflectionException
     */
    public function resolve(
        array  &$parameters,
        mixed  $parameterValue,
        string $parameterName,
        string $typeName,
        ?bool  $debug = false,
    ): void
    {
        $reflection = new ReflectionEnum($typeName);

        $resolveCallback = match (true) {
            $reflection->hasCase($parameterValue) => fn(): UnitEnum => $reflection->getCase($parameterValue)->getValue(),
            $reflection->isBacked() => function () use ($reflection, $parameterValue): ?UnitEnum {
                foreach ($reflection->getCases() as $case) {
                    if ($case->getValue()->value != $parameterValue) {
                        continue;
                    }

                    return $case->getValue();
                }

                return null;
            },
            default => fn() => null
        };

        $value = $resolveCallback();

        if ($value === null) {
            throw (new InputValueException())->data([
                'parameter' => $parameterName,
                'enum' => $typeName
            ])->debug($debug);
        }

        $parameters[$parameterName] = $resolveCallback();

        if (!array_key_exists($parameterName, $parameters)) {
            throw (new InputValueException())->data([
                'parameter' => $parameterName,
                'enum' => $typeName
            ])->debug($debug);
        }
    }
}