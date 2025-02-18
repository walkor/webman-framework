<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use support\exception\InputTypeException;
use support\exception\InputValueException;
use Webman\App;
use Webman\Container;
use Webman\Http\Request;

final class AppResolveMethodDependenciesTest extends TestCase
{
    public function setUp(): void
    {
        $this->app = new ReflectionClass(App::class);
        $this->container = new Container();
        $this->request = new Request('');
    }

    public function testResolveMethodDependenciesWithValidParameters()
    {
        $reflector = new \ReflectionMethod(\Tests\Stub\StubClass::class, 'stubMethod');
        $method = $this->app->getMethod('resolveMethodDependencies');
        $inputs = [
            'stringClassValue' => 'test_string',
            'intClassValue' => 1,
            'boolClassValue' => '1',
            'arrayClassValue' => ['array' => '1'],
            'objectClassValue' => ['object' => '1'],
            'enumClassValue' => 'TEST',
        ];

        $result = $method->invoke(null, $this->container, $this->request, $inputs, $reflector, false);

        $this->assertArrayHasKey('stringClassValue', $result);
        $this->assertArrayHasKey('intClassValue', $result);
        $this->assertArrayHasKey('boolClassValue', $result);
        $this->assertArrayHasKey('arrayClassValue', $result);
        $this->assertArrayHasKey('enumClassValue', $result);
        $this->assertIsString($result['stringClassValue']);
        $this->assertIsInt($result['intClassValue']);
        $this->assertIsBool($result['boolClassValue']);
        $this->assertIsArray($result['arrayClassValue']);
        $this->assertInstanceOf(\Tests\Stub\StubEnum::class, $result['enumClassValue']);
    }

    public function testResolveMethodDependenciesWithInvalidComplexValueParameter()
    {
        $reflector = new \ReflectionMethod(\Tests\Stub\StubClass::class, 'stubMethod');
        $method = $this->app->getMethod('resolveMethodDependencies');
        $inputs = [
            'stringClassValue' => 'test_string',
            'intClassValue' => 1,
            'boolClassValue' => '1',
            'arrayClassValue' => ['array' => '1'],
            'objectClassValue' => (object)['object' => '1'], // invalid value
            'enumClassValue' => 'TEST',
        ];
        $this->expectException(InputTypeException::class);

        $method->invoke(null, $this->container, $this->request, $inputs, $reflector, false);
    }

    public function testResolveMethodDependenciesWithInvalidNumericValueParameter()
    {
        $reflector = new \ReflectionMethod(\Tests\Stub\StubClass::class, 'stubMethod');
        $method = $this->app->getMethod('resolveMethodDependencies');
        $inputs = [
            'stringClassValue' => 'test_string',
            'intClassValue' => 'John Doe', // invalid value
            'boolClassValue' => '1',
            'arrayClassValue' => ['array' => '1'],
            'objectClassValue' => ['object' => '1'],
            'enumClassValue' => 'TEST',
        ];
        $this->expectException(InputTypeException::class);

        $method->invoke(null, $this->container, $this->request, $inputs, $reflector, false);
    }

    public function testResolveMethodDependenciesWithInvalidEnumValueParameter()
    {
        $reflector = new \ReflectionMethod(\Tests\Stub\StubClass::class, 'stubMethod');
        $method = $this->app->getMethod('resolveMethodDependencies');
        $inputs = [
            'stringClassValue' => 'test_string',
            'intClassValue' => 1,
            'boolClassValue' => '1',
            'arrayClassValue' => ['array' => '1'],
            'objectClassValue' => ['object' => '1'],
            'enumClassValue' => '1', // invalid value
        ];
        $this->expectException(InputValueException::class);

        $method->invoke(null, $this->container, $this->request, $inputs, $reflector, false);
    }


    public function testResolveMethodDependenciesWithInvalidIsBackendEnumValueParameter()
    {
        $reflector = new \ReflectionMethod(\Tests\Stub\StubClass::class, 'stubMethodForIsBackendEnum');
        $method = $this->app->getMethod('resolveMethodDependencies');
        $inputs = [
            'enumClassValue' => '1', // not exist value
        ];
        $this->expectException(InputValueException::class);

        $method->invoke(null, $this->container, $this->request, $inputs, $reflector, false);
    }

    public function testResolveMethodDependenciesWithValidIsBackendEnumValueParameter()
    {
        $reflector = new \ReflectionMethod(\Tests\Stub\StubClass::class, 'stubMethodForIsBackendEnum');
        $method = $this->app->getMethod('resolveMethodDependencies');
        $inputs = [
            'enumClassValue' => 'test',
        ];

        $result = $method->invoke(null, $this->container, $this->request, $inputs, $reflector, false);

        $this->assertArrayHasKey('enumClassValue', $result);
        $this->assertInstanceOf(\Tests\Stub\StubIsBackendEnum::class, $result['enumClassValue']);
    }
}