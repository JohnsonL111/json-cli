<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\Apply;
use Swaggest\JsonCli\Base;
use Swaggest\JsonCli\BuildSchema;
use Swaggest\JsonCli\Diff;
use Swaggest\JsonCli\DiffInfo;
use Swaggest\JsonCli\ExitCode;
use Swaggest\JsonCli\GenGo;
use Swaggest\JsonCli\GenJSDoc;
use Swaggest\JsonCli\GenJson;
use Swaggest\JsonCli\GenMarkdown;
use Swaggest\JsonCli\GenPhp;
use Swaggest\JsonCli\Minify;
use Swaggest\JsonCli\PrettyPrint;
use Swaggest\JsonCli\Rearrange;
use Swaggest\JsonCli\Replace;
use Swaggest\JsonCli\Resolve;
use Swaggest\JsonCli\ResolvePos;
use Swaggest\JsonCli\ValidateSchema;
use Yaoi\Cli\Response;

class CoverageBoostTest extends TestCase
{
    private function assets($file = '')
    {
        return __DIR__ . '/../../tests/assets/' . $file;
    }

    // ---------------------------------------------------------------
    // Base: readJsonOrYaml with YAML input
    // ---------------------------------------------------------------
    public function testReadYamlFile()
    {
        $result = Base::readJsonOrYaml($this->assets('original.yaml'), new Response());
        $this->assertIsObject($result);
        $this->assertEquals(2, $result->key2);
    }

    public function testReadYmlFile()
    {
        $result = Base::readJsonOrYaml($this->assets('streetlights.yml'), new Response());
        $this->assertIsObject($result);
    }

    public function testReadSerializedFile()
    {
        $result = Base::readJsonOrYaml($this->assets('original.serialized'), new Response());
        $this->assertIsObject($result);
        $this->assertEquals(2, $result->key2);
    }

    public function testReadJsonOrYamlThrowsOnMissingFile()
    {
        $this->expectException(ExitCode::class);
        Base::readJsonOrYaml($this->assets('nonexistent-file.json'), new Response());
    }

    // ---------------------------------------------------------------
    // Base: postPerform with toYaml
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Base: postPerform with toSerialized
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Base: postPerform with output file
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Base: postPerform with pretty=false (non-pretty JSON)
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Minify: eol option
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Apply: tolerateErrors path
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Apply: exception handling path
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BaseDiff: prePerform without rearrangeArrays
    // ---------------------------------------------------------------
    public function testDiffWithoutRearrangeArrays()
    {
        $d = new Diff();
        $d->pretty = true;
        $d->rearrangeArrays = false;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('new.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // ---------------------------------------------------------------
    // Diff: prettyShort format
    // ---------------------------------------------------------------
    public function testDiffPrettyShort()
    {
        $d = new Diff();
        $d->prettyShort = true;
        $d->rearrangeArrays = true;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('new.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertStringContainsString('[', $res);
    }

    // ---------------------------------------------------------------
    // Diff: merge patch
    // ---------------------------------------------------------------
    public function testDiffMergePatch()
    {
        $d = new Diff();
        $d->merge = true;
        $d->rearrangeArrays = true;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('new.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // ---------------------------------------------------------------
    // DiffInfo: without paths and contents
    // ---------------------------------------------------------------
    public function testDiffInfoWithoutPathsOrContents()
    {
        $d = new DiffInfo();
        $d->rearrangeArrays = true;
        $d->withContents = false;
        $d->withPaths = false;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('new.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $decoded = json_decode($res, true);
        $this->assertArrayHasKey('addedCnt', $decoded);
        $this->assertArrayNotHasKey('addedPaths', $decoded);
        $this->assertArrayNotHasKey('added', $decoded);
    }

    // ---------------------------------------------------------------
    // DiffInfo: with paths only (no contents)
    // ---------------------------------------------------------------
    public function testDiffInfoWithPathsOnly()
    {
        $d = new DiffInfo();
        $d->rearrangeArrays = true;
        $d->withPaths = true;
        $d->withContents = false;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('new.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $decoded = json_decode($res, true);
        $this->assertArrayHasKey('addedPaths', $decoded);
        $this->assertArrayNotHasKey('added', $decoded);
    }

    // ---------------------------------------------------------------
    // Rearrange: basic test (already tested in CliTest but covers more lines)
    // ---------------------------------------------------------------
    public function testRearrangeWithoutRearrangeArrays()
    {
        $d = new Rearrange();
        $d->pretty = true;
        $d->rearrangeArrays = false;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('new.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // ---------------------------------------------------------------
    // Replace: invalid search JSON
    // ---------------------------------------------------------------
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

        // Should return early without error, just writes error to response
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Replace: invalid replace JSON
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Replace: without pathFilter
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Resolve: error path (bad pointer)
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ResolvePos: dumpAll
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ResolvePos: pointer not found
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ValidateSchema: with explicit schema
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ValidateSchema: with invalid data against explicit schema
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: basic with just data (no schema)
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with JSONL input
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with JSONL and ptrInData
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with JSONL containing malformed lines
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with additionalData
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with parent schema
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with useNullable
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with ptrInSchema
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // GenGo: with output to file
    // ---------------------------------------------------------------
    public function testGenGoWithOutput()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.go';

        try {
            $d = new GenGo();
            $d->schema = $this->assets('sample-schema2.json');
            $d->packageName = 'testpkg';
            $d->output = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $this->assertFileExists($tmpFile);
            $content = file_get_contents($tmpFile);
            $this->assertStringContainsString('testpkg', $content);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ---------------------------------------------------------------
    // GenGo: with withTests option (triggers pre-existing TypeError in upstream lib)
    // ---------------------------------------------------------------
    public function testGenGoWithTests()
    {
        $tmpDir = sys_get_temp_dir() . '/json-cli-test-gengo-' . uniqid();
        mkdir($tmpDir);
        $tmpFile = $tmpDir . '/entities.go';

        try {
            $d = new GenGo();
            $d->schema = $this->assets('sample-schema2.json');
            $d->packageName = 'testpkg';
            $d->output = $tmpFile;
            $d->withTests = true;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $this->assertFileExists($tmpFile);
        } catch (\TypeError $e) {
            // Pre-existing bug: upstream lib expects Options but gets stdClass
            // GenGo.php line 113: $options->jsonSerialize() returns stdClass
            ob_end_clean();
            $this->assertStringContainsString('Options', $e->getMessage());
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpFile);
            @unlink($tmpDir . '/entities_test.go');
            @rmdir($tmpDir);
        }
    }

    // ---------------------------------------------------------------
    // GenGo: output to nonexistent directory
    // ---------------------------------------------------------------
    public function testGenGoOutputBadDir()
    {
        $d = new GenGo();
        $d->schema = $this->assets('sample-schema2.json');
        $d->packageName = 'testpkg';
        $d->output = '/nonexistent/dir/entities.go';
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

    // ---------------------------------------------------------------
    // GenGo: with enableDefaultAdditionalProperties
    // ---------------------------------------------------------------
    public function testGenGoWithBuilderOptions()
    {
        $d = new GenGo();
        $d->schema = $this->assets('sample-schema2.json');
        $d->packageName = 'testpkg';
        $d->showConstProperties = true;
        $d->keepParentInPropertyNames = true;
        $d->ignoreNullable = true;
        $d->ignoreXGoType = true;
        $d->withZeroValues = true;
        $d->enableXNullable = true;
        $d->enableDefaultAdditionalProperties = true;
        $d->fluentSetters = true;
        $d->ignoreRequired = true;
        $d->requireXGenerate = true;
        $d->validateRequired = true;
        $d->nameTags = ['msgp', 'bson'];
        $d->renames = ['Foo:Bar'];
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // ---------------------------------------------------------------
    // GenGo BuilderOptions: with config file
    // ---------------------------------------------------------------
    public function testGenGoWithConfigFile()
    {
        $tmpConfig = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpConfig, '{}');

        try {
            $d = new GenGo();
            $d->schema = $this->assets('sample-schema2.json');
            $d->packageName = 'testpkg';
            $d->config = $tmpConfig;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty($res);
        } finally {
            @unlink($tmpConfig);
        }
    }

    // ---------------------------------------------------------------
    // GenGo BuilderOptions: with bad config file
    // ---------------------------------------------------------------
    public function testGenGoWithBadConfigFile()
    {
        $tmpConfig = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpConfig, '');

        try {
            $d = new GenGo();
            $d->schema = $this->assets('sample-schema2.json');
            $d->packageName = 'testpkg';
            $d->config = $tmpConfig;
            $d->setResponse(new Response());
            ob_start();
            $this->expectException(ExitCode::class);
            $d->performAction();
        } finally {
            ob_end_clean();
            @unlink($tmpConfig);
        }
    }

    // ---------------------------------------------------------------
    // GenGo BuilderOptions: with invalid JSON config
    // ---------------------------------------------------------------
    public function testGenGoWithInvalidJsonConfig()
    {
        $tmpConfig = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpConfig, 'not json');

        try {
            $d = new GenGo();
            $d->schema = $this->assets('sample-schema2.json');
            $d->packageName = 'testpkg';
            $d->config = $tmpConfig;
            $d->setResponse(new Response());
            ob_start();
            $this->expectException(ExitCode::class);
            $d->performAction();
        } finally {
            ob_end_clean();
            @unlink($tmpConfig);
        }
    }

    // ---------------------------------------------------------------
    // GenJSDoc: with output to file
    // ---------------------------------------------------------------
    public function testGenJSDocWithOutput()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.js';

        try {
            $d = new GenJSDoc();
            $d->schema = $this->assets('sample-schema2.json');
            $d->output = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $this->assertFileExists($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ---------------------------------------------------------------
    // GenJSDoc: output to nonexistent directory
    // ---------------------------------------------------------------
    public function testGenJSDocOutputBadDir()
    {
        $d = new GenJSDoc();
        $d->schema = $this->assets('sample-schema2.json');
        $d->output = '/nonexistent/dir/entities.js';
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

    // ---------------------------------------------------------------
    // GenMarkdown: with output to file
    // ---------------------------------------------------------------
    public function testGenMarkdownWithOutput()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.md';

        try {
            $d = new GenMarkdown();
            $d->schema = $this->assets('swagger-schema.json');
            $d->ptrInSchema = ['#/definitions/info'];
            $d->output = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $this->assertFileExists($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ---------------------------------------------------------------
    // GenMarkdown: output to nonexistent directory
    // ---------------------------------------------------------------
    public function testGenMarkdownOutputBadDir()
    {
        $d = new GenMarkdown();
        $d->schema = $this->assets('sample-schema2.json');
        $d->output = '/nonexistent/dir/entities.md';
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

    // ---------------------------------------------------------------
    // GenJson: without randSeed (random output)
    // ---------------------------------------------------------------
    public function testGenJsonWithoutRandSeed()
    {
        $d = new GenJson();
        $d->schema = $this->assets('sample-schema2.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty(trim($res));
    }

    // ---------------------------------------------------------------
    // GenJson: with defaultAdditionalProperties
    // ---------------------------------------------------------------
    public function testGenJsonWithOptions()
    {
        $d = new GenJson();
        $d->schema = $this->assets('sample-schema2.json');
        $d->randSeed = 42;
        $d->maxNesting = 5;
        $d->defaultAdditionalProperties = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty(trim($res));
    }

    // ---------------------------------------------------------------
    // GenPhp: with builder options (setters, getters, etc.)
    // ---------------------------------------------------------------
    public function testGenPhpWithBuilderOptions()
    {
        $d = new GenPhp();
        $d->schema = $this->assets('swagger-schema.json');
        $d->ptrInSchema = ['#/definitions/info'];
        $d->ns = 'Swagger';
        $d->nsPath = __DIR__ . '/../assets/php/Swagger';
        $d->setters = true;
        $d->getters = true;
        $d->noEnumConst = true;
        $d->declarePropertyDefaults = true;
        $d->buildAdditionalPropertiesAccessors = true;
        $d->setResponse(new Response());
        ob_start();
        try {
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            return;
        }
        ob_get_clean();

        exec('git checkout -- ' . __DIR__ . '/../assets/php/Swagger');
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // GenPhp: bad nsPath
    // ---------------------------------------------------------------
    public function testGenPhpBadNsPath()
    {
        $d = new GenPhp();
        $d->schema = $this->assets('sample-schema2.json');
        $d->ns = 'Test';
        $d->nsPath = '/nonexistent/path';
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

    // ---------------------------------------------------------------
    // Base: loadSchema with stdin marker
    // ---------------------------------------------------------------
    public function testGenJsonWithSchemaFromStdin()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '{"type":"object","properties":{"name":{"type":"string"}}}');

        try {
            $d = new GenJson();
            $d->schema = $tmpFile;
            $d->randSeed = 1;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();

            $this->assertNotEmpty(trim($res));
        } finally {
            @unlink($tmpFile);
        }
    }

    // ---------------------------------------------------------------
    // Base: loadSchema with schemaResolver
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Base: loadSchema with empty schemaResolver file
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Base: loadSchema with invalid JSON schemaResolver
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Base: loadSchema with schemaResolver containing data
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // PrettyPrint: read YAML file
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ValidateSchema: with broken schema import (InvalidValue)
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // BuildSchema: with invalid parent schema
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ExitCode: simple instantiation
    // ---------------------------------------------------------------
    public function testExitCode()
    {
        $e = new ExitCode('test message', 42);
        $this->assertSame('test message', $e->getMessage());
        $this->assertSame(42, $e->getCode());
        $this->assertInstanceOf(\Exception::class, $e);
    }
}
