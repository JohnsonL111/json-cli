<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\App;
use Swaggest\JsonCli\GenJSDoc;
use Yaoi\Cli\Response;

class GenJSDocTest extends TestCase
{
    public function testSwagger()
    {
        $d = new GenJSDoc();
        $d->schema = __DIR__ . '/../../tests/assets/swagger-schema.json';
        $d->ptrInSchema = ['#/definitions/info'];

        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertSame(
            str_replace('<version>', App::$ver,
                file_get_contents(__DIR__ . '/../../tests/assets/jsdoc/swagger/entities.js')),
            $res
        );

    }

    public function testAsyncAPI()
    {
        $d = new GenJSDoc();
        $d->schema = __DIR__ . '/../../tests/assets/asyncapi-schema.json';
        $d->defPtr = [
            'http://json-schema.org/draft-04/schema#/definitions',
            'http://json-schema.org/draft-04/schema',
            '#/definitions',
        ];

        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertSame(
            str_replace('<version>', App::$ver,
                file_get_contents(__DIR__ . '/../../tests/assets/jsdoc/asyncapi/entities.js')),
            $res
        );

    }

    public function testAsyncAPIStreetLights()
    {
        $d = new GenJSDoc();
        $d->schema = __DIR__ . '/../../tests/assets/streetlights.yml';
        $d->ptrInSchema = [
            '#/components/messages/lightMeasured/payload',
            '#/components/messages/turnOnOff/payload',
            '#/components/messages/dimLight/payload',
        ];
        $d->defPtr = ['#/components/schemas'];
        $d->patches = [
            __DIR__ . '/../assets/streetlights-patch.json',
            __DIR__ . '/../assets/streetlights-merge-patch.json',
        ];

        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertSame(
            str_replace('<version>', App::$ver,
                file_get_contents(__DIR__ . '/../../tests/assets/jsdoc/asyncapi-streetlights/entities.js')),
            $res
        );

    }

}