<?php
declare(strict_types=1);

namespace Tests\Stub;


final class StubClass
{
    public function stubMethod(
        string   $stringClassValue,
        int      $intClassValue,
        bool     $boolClassValue,
        array    $arrayClassValue,
        object   $objectClassValue,
        StubEnum $enumClassValue,
    )
    {

    }

    public function stubMethodForIsBackendEnum(
        StubIsBackendEnum $enumClassValue,
    )
    {

    }
}