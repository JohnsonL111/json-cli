<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\Base;
use Swaggest\JsonCli\ExitCode;
use Swaggest\JsonCli\GenJson;
use Swaggest\JsonCli\PrettyPrint;
use Yaoi\Cli\Response;

class StubGenJsonNoStdinBlock extends GenJson
{
    protected function loadFile()
    {
        return (object)['type' => 'string'];
    }
}

class BaseTest extends TestCase
{
    private function assets($file = '')
    {
        return __DIR__ . '/../../tests/assets/' . $file;
    }

    // tests YAML file reading via readJsonOrYaml
    public function testReadYamlFile()
    {
        $result = Base::readJsonOrYaml($this->assets('original.yaml'), new Response());
        $this->assertIsObject($result);
        $this->assertEquals(2, $result->key2);
    }

    // tests .yml file reading via readJsonOrYaml
    public function testReadYmlFile()
    {
        $result = Base::readJsonOrYaml($this->assets('streetlights.yml'), new Response());
        $this->assertIsObject($result);
    }

    // tests PHP serialized file reading via readJsonOrYaml
    public function testReadSerializedFile()
    {
        $result = Base::readJsonOrYaml($this->assets('original.serialized'), new Response());
        $this->assertIsObject($result);
        $this->assertEquals(2, $result->key2);
    }

    // tests exception on missing file
    public function testReadJsonOrYamlThrowsOnMissingFile()
    {
        $this->expectException(ExitCode::class);
        Base::readJsonOrYaml($this->assets('nonexistent-file.json'), new Response());
    }

    // tests postPerform with toYaml output
    public function testPrettyPrintToYaml()
    {
        $d = new PrettyPrint();
        $d->path = $this->assets('original.json');
        $d->toYaml = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringContainsString('key1:', $res);
        $this->assertStringContainsString('key2: 2', $res);
    }

    // tests postPerform with toSerialized output
    public function testPrettyPrintToSerialized()
    {
        $d = new PrettyPrint();
        $d->path = $this->assets('original.json');
        $d->toSerialized = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $unserialized = unserialize($res);
        $this->assertEquals(2, $unserialized->key2);
    }

    // tests postPerform with file output
    public function testPrettyPrintToFile()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        try {
            $d = new PrettyPrint();
            $d->path = $this->assets('original.json');
            $d->output = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $content = file_get_contents($tmpFile);
            $this->assertJson($content);
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests reading YAML input via PrettyPrint
    public function testPrettyPrintYamlInput()
    {
        $d = new PrettyPrint();
        $d->path = $this->assets('original.yaml');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertJson(rtrim($res));
    }

    // tests loadSchema stdin shorthand (schema === '-')
    public function testBaseLoadSchemaStdinShorthand()
    {
        $cmd = new StubGenJsonNoStdinBlock();
        $cmd->schema = '-';
        $cmd->randSeed = 1;
        $cmd->setResponse(new Response());

        $method = new \ReflectionMethod($cmd, 'loadSchema');
        $method->setAccessible(true);

        $skipRoot = false;
        $baseName = null;
        try {
            $method->invokeArgs($cmd, [&$skipRoot, &$baseName]);
        } catch (\Throwable $e) {
            // Expected — stub returns minimal data that may not import cleanly
        }

        // Line 152 changed schema from '-' to 'php://stdin'
        $this->assertSame('php://stdin', $cmd->schema);
    }

    // tests loadSchema with schemaResolver
    public function testLoadSchemaWithSchemaResolver()
    {
        $tmpResolver = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpResolver, json_encode([
            'schemaData' => new \stdClass(),
            'schemaFiles' => new \stdClass(),
        ]));

        try {
            $d = new GenJson();
            $d->schema = $this->assets('sample-schema2.json');
            $d->randSeed = 1;
            $d->schemaResolver = $tmpResolver;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpResolver);
        }
    }

    // tests loadSchema with empty schemaResolver file
    public function testLoadSchemaWithEmptySchemaResolver()
    {
        $tmpResolver = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpResolver, '');

        try {
            $d = new GenJson();
            $d->schema = $this->assets('sample-schema2.json');
            $d->schemaResolver = $tmpResolver;
            $d->setResponse(new Response());
            ob_start();
            $this->expectException(ExitCode::class);
            $d->performAction();
        } finally {
            ob_end_clean();
            @unlink($tmpResolver);
        }
    }

    // tests loadSchema with invalid JSON schemaResolver
    public function testLoadSchemaWithInvalidSchemaResolver()
    {
        $tmpResolver = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpResolver, 'not json at all');

        try {
            $d = new GenJson();
            $d->schema = $this->assets('sample-schema2.json');
            $d->schemaResolver = $tmpResolver;
            $d->setResponse(new Response());
            ob_start();
            $this->expectException(ExitCode::class);
            $d->performAction();
        } finally {
            ob_end_clean();
            @unlink($tmpResolver);
        }
    }

    // tests loadSchema with schemaResolver containing data
    public function testLoadSchemaWithSchemaResolverData()
    {
        $tmpResolver = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        $schemaData = new \stdClass();
        $schemaData->{'http://example.com/schema.json'} = (object)['type' => 'string'];
        file_put_contents($tmpResolver, json_encode([
            'schemaData' => $schemaData,
            'schemaFiles' => new \stdClass(),
        ]));

        try {
            $d = new GenJson();
            $d->schema = $this->assets('sample-schema2.json');
            $d->randSeed = 1;
            $d->schemaResolver = $tmpResolver;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpResolver);
        }
    }

    // tests loadSchema with schemaResolver containing files
    public function testLoadSchemaWithSchemaFiles()
    {
        $tmpResolver = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        $schemaFiles = new \stdClass();
        $schemaFiles->{'http://example.com/myschema.json'} = $this->assets('sample-schema2.json');
        file_put_contents($tmpResolver, json_encode([
            'schemaData' => new \stdClass(),
            'schemaFiles' => $schemaFiles,
        ]));

        try {
            $d = new GenJson();
            $d->schema = $this->assets('sample-schema2.json');
            $d->randSeed = 1;
            $d->schemaResolver = $tmpResolver;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpResolver);
        }
    }

    // tests ExitCode exception instantiation
    public function testExitCode()
    {
        $e = new ExitCode('test message', 42);
        $this->assertSame('test message', $e->getMessage());
        $this->assertSame(42, $e->getCode());
        $this->assertInstanceOf(\Exception::class, $e);
    }
}
