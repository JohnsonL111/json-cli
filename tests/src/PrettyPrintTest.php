<?php

namespace Swaggest\JsonCli\Tests;


use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\PrettyPrint;
use Yaoi\Cli\Response;

class PrettyPrintTest extends TestCase
{
    public function testPrettyPrint()
    {
        $d = new PrettyPrint();
        $d->path = __DIR__ . '/../../tests/assets/original-minified.json';
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertSame(
            file_get_contents(__DIR__ . '/../../tests/assets/original.json'),
            rtrim($res)
        );

    }
}