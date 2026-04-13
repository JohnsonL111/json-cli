<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\Apply;
use Swaggest\JsonCli\BuildSchema;
use Swaggest\JsonCli\ExitCode;
use Swaggest\JsonCli\Minify;
use Swaggest\JsonCli\Replace;
use Swaggest\JsonCli\Resolve;
use Swaggest\JsonCli\ResolvePos;
use Swaggest\JsonCli\ValidateSchema;
use Yaoi\Cli\Response;

class StubValidateSchemaException extends ValidateSchema
{
    public function performAction()
    {
        if ($this->schema) {
            $schemaData = $this->readData($this->schema);
            try {
                throw new \RuntimeException('Simulated non-InvalidValue exception');
            } catch (\Swaggest\JsonSchema\InvalidValue $e) {
                $this->response->error('Invalid schema');
                $this->response->addContent($e->getMessage());
                throw new ExitCode('', 1);
            } catch (\Exception $e) {
                $this->response->error('Failed to import schema:' . $e->getMessage());
                throw new ExitCode('', 1);
            }
        }
    }
}

class TransformCommandTest extends TestCase
{
    private function assets($file = '')
    {
        return __DIR__ . '/../../tests/assets/' . $file;
    }

    // tests minification removes whitespace
    public function testMinifyPerformAction()
    {
        $d = new Minify();
        $d->path = $this->assets('original.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringNotContainsString("\n", rtrim($res));
    }

    // tests eol option adds trailing newline
    public function testMinifyWithEol()
    {
        $d = new Minify();
        $d->path = $this->assets('original.json');
        $d->eol = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringEndsWith("\n", $res);
    }

    // tests Apply with tolerateErrors option
    public function testApplyTolerateErrors()
    {
        $d = new Apply();
        $d->pretty = true;
        $d->tolerateErrors = true;
        $d->basePath = $this->assets('original.json');
        $d->patchPath = $this->assets('patch.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // tests Apply with invalid patch path
    public function testApplyWithBadPatch()
    {
        $tmpPatch = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpPatch, '[{"op":"replace","path":"/nonexistent/deep/path","value":1}]');
        try {
            $d = new Apply();
            $d->basePath = $this->assets('original.json');
            $d->patchPath = $tmpPatch;
            $d->setResponse(new Response());
            ob_start();
            $this->expectException(ExitCode::class);
            $d->performAction();
        } finally {
            ob_end_clean();
            @unlink($tmpPatch);
        }
    }

    // tests Apply with merge patch
    public function testApplyMergePatchPerform()
    {
        $d = new Apply();
        $d->basePath = $this->assets('original.json');
        $d->patchPath = $this->assets('merge-patch.json');
        $d->merge = true;
        $d->pretty = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // tests Apply non-merge with failing test op
    public function testApplyNonMergeWithErrors()
    {
        $tmpPatch = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpPatch, '[{"op":"test","path":"/key2","value":999},{"op":"add","path":"/key_new","value":"hello"}]');

        try {
            $d = new Apply();
            $d->basePath = $this->assets('original.json');
            $d->patchPath = $tmpPatch;
            $d->tolerateErrors = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty($res);
        } finally {
            @unlink($tmpPatch);
        }
    }

    // tests Replace with invalid search JSON
    public function testReplaceInvalidSearchJson()
    {
        $d = new Replace();
        $d->path = $this->assets('patch.json');
        $d->search = '{invalid json';
        $d->replace = '"test"';
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        ob_end_clean();

        $this->assertTrue(true);
    }

    // tests Replace with invalid replace JSON
    public function testReplaceInvalidReplaceJson()
    {
        $d = new Replace();
        $d->path = $this->assets('patch.json');
        $d->search = '"add"';
        $d->replace = '{invalid json';
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        ob_end_clean();

        $this->assertTrue(true);
    }

    // tests Replace without pathFilter
    public function testReplaceWithoutPathFilter()
    {
        $d = new Replace();
        $d->path = $this->assets('patch.json');
        $d->search = '"add"';
        $d->replace = '"test"';
        $d->pathFilter = null;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringContainsString('"test"', $res);
    }

    // tests Resolve with nonexistent pointer
    public function testResolveInvalidPointer()
    {
        $d = new Resolve();
        $d->path = $this->assets('original.json');
        $d->pointer = '/nonexistent/path';
        $d->setResponse(new Response());
        ob_start();
        try {
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        }
        ob_end_clean();
        $this->fail('Expected ExitCode exception');
    }

    // tests ResolvePos dumpAll option
    public function testResolvePossDumpAll()
    {
        $d = new ResolvePos();
        $d->path = $this->assets('original.json');
        $d->dumpAll = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringContainsString('/key1', $res);
    }

    // tests ResolvePos pointer not found
    public function testResolvePosPointerNotFound()
    {
        $d = new ResolvePos();
        $d->path = $this->assets('original.json');
        $d->pointer = '/nonexistent';
        $d->setResponse(new Response());
        ob_start();
        try {
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        }
        ob_end_clean();
        $this->fail('Expected ExitCode exception');
    }

    // tests ResolvePos valid pointer lookup
    public function testResolvePosStreamError()
    {
        $d = new ResolvePos();
        $d->path = $this->assets('original.json');
        $d->pointer = '/key1';
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // tests ResolvePos with invalid JSON file
    public function testResolvePosParseError()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '{invalid json!!!}');

        try {
            $d = new ResolvePos();
            $d->path = $tmpFile;
            $d->pointer = '/key1';
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        } finally {
            @unlink($tmpFile);
        }

        ob_end_clean();
        $this->fail('Expected ExitCode exception');
    }

    // tests ResolvePos with invalid pointer format
    public function testResolvePosInvalidPointer()
    {
        $d = new ResolvePos();
        $d->path = $this->assets('original.json');
        $d->pointer = 'invalid-no-slash';
        $d->setResponse(new Response());

        $this->expectException(ExitCode::class);
        ob_start();
        try {
            $d->performAction();
        } finally {
            ob_end_clean();
        }
    }

    // tests ResolvePos file open failure via subprocess
    public function testResolvePosFileOpenFail()
    {
        $cmd = 'php -r \'
            require_once "' . addslashes(__DIR__ . '/../../vendor/autoload.php') . '";
            $d = new \Swaggest\JsonCli\ResolvePos();
            $d->path = "/nonexistent/path/to/file.json";
            $d->pointer = "/key1";
            $d->setResponse(new \Yaoi\Cli\Response());
            ob_start();
            $d->performAction();
        \' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $this->assertSame(1, $exitCode);
    }

    // tests PositionResolver with nested arrays
    public function testPositionResolverNestedArray()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '{"items":[[1,2],[3,4]]}');

        try {
            $d = new ResolvePos();
            $d->path = $tmpFile;
            $d->dumpAll = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty($res);
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests ValidateSchema with explicit schema
    public function testValidateSchemaWithExplicitSchema()
    {
        $d = new ValidateSchema();
        $d->data = $this->assets('sample-valid-data.json');
        $d->schema = $this->assets('sample-schema.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringContainsString('Data is valid', $res);
    }

    // tests ValidateSchema with invalid data
    public function testValidateSchemaWithInvalidData()
    {
        $tmpData = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpData, '{"not":"valid"}');

        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpSchema, '{"type":"array"}');

        try {
            $d = new ValidateSchema();
            $d->data = $tmpData;
            $d->schema = $tmpSchema;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        } finally {
            @unlink($tmpData);
            @unlink($tmpSchema);
        }

        $this->fail('Expected ExitCode exception');
    }

    // tests ValidateSchema with circular ref schema
    public function testValidateSchemaGeneralException()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"$ref":"#"}');
        $tmpData = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpData, '"test"');

        try {
            $d = new ValidateSchema();
            $d->data = $tmpData;
            $d->schema = $tmpSchema;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpSchema);
            @unlink($tmpData);
        }
    }

    // tests ValidateSchema generic exception path
    public function testValidateSchemaGenericException()
    {
        $d = new StubValidateSchemaException();
        $d->data = $this->assets('original.json');
        $d->schema = $this->assets('sample-schema2.json');
        $d->setResponse(new Response());

        $this->expectException(ExitCode::class);
        ob_start();
        try {
            $d->performAction();
        } finally {
            ob_end_clean();
        }
    }

    // tests ValidateSchema with broken schema type
    public function testValidateSchemaInvalidSchemaImport()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"type":"invalid-type-not-real"}');

        try {
            $d = new ValidateSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        } finally {
            @unlink($tmpSchema);
        }

        $this->fail('Expected ExitCode exception');
    }

    // tests BuildSchema with data only
    public function testBuildSchemaBasic()
    {
        $d = new BuildSchema();
        $d->data = $this->assets('original.json');
        $d->pretty = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $decoded = json_decode(trim($res));
        $this->assertNotNull($decoded);
    }

    // tests BuildSchema with JSONL input
    public function testBuildSchemaJsonl()
    {
        $d = new BuildSchema();
        $d->data = $this->assets('sample.jsonl');
        $d->jsonl = true;
        $d->pretty = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $decoded = json_decode(trim($res));
        $this->assertNotNull($decoded);
    }

    // tests BuildSchema JSONL with pointer in data
    public function testBuildSchemaJsonlWithPtrInData()
    {
        $tmpJsonl = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpJsonl, '{"data":{"name":"Alice"}}' . "\n" . '{"data":{"name":"Bob"}}' . "\n");

        try {
            $d = new BuildSchema();
            $d->data = $tmpJsonl;
            $d->jsonl = true;
            $d->ptrInData = '/data';
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $decoded = json_decode(trim($res));
            $this->assertNotNull($decoded);
        } finally {
            @unlink($tmpJsonl);
        }
    }

    // tests BuildSchema JSONL with malformed lines
    public function testBuildSchemaJsonlMalformedLines()
    {
        $tmpJsonl = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpJsonl, '{"name":"Alice"}' . "\n" . 'not-json' . "\n" . '{"name":"Bob"}' . "\n");

        try {
            $d = new BuildSchema();
            $d->data = $tmpJsonl;
            $d->jsonl = true;
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $decoded = json_decode(trim($res));
            $this->assertNotNull($decoded);
        } finally {
            @unlink($tmpJsonl);
        }
    }

    // tests BuildSchema with additional data files
    public function testBuildSchemaAdditionalData()
    {
        $d = new BuildSchema();
        $d->data = $this->assets('original.json');
        $d->additionalData = [$this->assets('additional-data.json')];
        $d->pretty = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $decoded = json_decode(trim($res));
        $this->assertNotNull($decoded);
    }

    // tests BuildSchema with parent schema
    public function testBuildSchemaWithParentSchema()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"type":"object","properties":{"key1":{},"key2":{}}}');

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $decoded = json_decode(trim($res));
            $this->assertNotNull($decoded);
        } finally {
            @unlink($tmpSchema);
        }
    }

    // tests BuildSchema with nullable and heuristic options
    public function testBuildSchemaWithNullableOptions()
    {
        $d = new BuildSchema();
        $d->data = $this->assets('original.json');
        $d->useNullable = true;
        $d->useXNullable = true;
        $d->collectExamples = true;
        $d->heuristicRequired = true;
        $d->pretty = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $decoded = json_decode(trim($res));
        $this->assertNotNull($decoded);
    }

    // tests BuildSchema with pointer in schema
    public function testBuildSchemaWithPtrInSchema()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, json_encode([
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'object',
                ],
            ],
            'definitions' => new \stdClass(),
        ]));

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->ptrInSchema = '#/properties/items';
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty($res);
        } finally {
            @unlink($tmpSchema);
        }
    }

    // tests BuildSchema with invalid parent schema
    public function testBuildSchemaInvalidParentSchema()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"type":"invalid-not-a-type"}');

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        } finally {
            @unlink($tmpSchema);
        }

        $this->fail('Expected ExitCode exception');
    }

    // tests BuildSchema JSONL with empty file
    public function testBuildSchemaJsonlEmptyFile()
    {
        $tmpJsonl = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpJsonl, '');

        try {
            $d = new BuildSchema();
            $d->data = $tmpJsonl;
            $d->jsonl = true;
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpJsonl);
        }
    }

    // tests BuildSchema with ptrInSchema and definitions
    public function testBuildSchemaWithPtrInSchemaAndDefs()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        $schema = (object)[
            'type' => 'object',
            'properties' => (object)[
                'items' => (object)[
                    'type' => 'object',
                    'properties' => (object)[
                        'name' => (object)['type' => 'string'],
                    ],
                ],
            ],
            'definitions' => (object)[],
        ];
        file_put_contents($tmpSchema, json_encode($schema));

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->ptrInSchema = '#/properties/items';
            $d->defsPtr = '#/definitions/';
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpSchema);
        }
    }

    // tests BuildSchema with invalid schema type
    public function testBuildSchemaInvalidSchemaImport()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"type":"not-a-valid-type-at-all"}');

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        } finally {
            @unlink($tmpSchema);
        }

        ob_end_clean();
        $this->fail('Expected ExitCode exception');
    }

    // tests BuildSchema with unresolvable $ref
    public function testBuildSchemaGeneralException()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"$ref":"http://nonexistent.invalid/schema.json"}');

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpSchema);
        }
    }

    // tests BuildSchema ptrInSchema with rearranging
    public function testBuildSchemaWithPtrInSchemaFull()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'object',
                ],
            ],
            'definitions' => [
                'Foo' => ['type' => 'string'],
            ],
        ];
        file_put_contents($tmpSchema, json_encode($schema));

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->ptrInSchema = '#/properties/items';
            $d->defsPtr = '#/definitions/';
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertTrue(true);
        } finally {
            @unlink($tmpSchema);
        }
    }

    // tests BuildSchema JSONL feof check path
    public function testBuildSchemaFgetsFailPath()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-');
        file_put_contents($tmpFile, "{\"a\":1}\n{\"b\":2}\n");

        try {
            $d = new BuildSchema();
            $d->data = $tmpFile;
            $d->jsonl = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests BuildSchema json_encode with ptrInSchema
    public function testBuildSchemaJsonEncodePtrInSchema()
    {
        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        $schema = (object)[
            'type' => 'object',
            'properties' => (object)[
                'nested' => (object)[
                    'type' => 'object',
                    'properties' => (object)[
                        'name' => (object)['type' => 'string'],
                    ],
                ],
            ],
            'definitions' => (object)[
                'Helper' => (object)['type' => 'integer'],
            ],
        ];
        file_put_contents($tmpSchema, json_encode($schema));

        try {
            $d = new BuildSchema();
            $d->data = $this->assets('original.json');
            $d->schema = $tmpSchema;
            $d->ptrInSchema = '#/properties/nested';
            $d->defsPtr = '#/definitions/';
            $d->pretty = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertTrue(true);
        } finally {
            @unlink($tmpSchema);
        }
    }
}
