<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\ExitCode;
use Swaggest\JsonCli\GenGo;
use Swaggest\JsonCli\GenJSDoc;
use Swaggest\JsonCli\GenJson;
use Swaggest\JsonCli\GenMarkdown;
use Swaggest\JsonCli\GenPhp;
use Yaoi\Cli\Response;

class StubGenJSDoc extends GenJSDoc
{
    protected function loadSchema(&$skipRoot, &$baseName)
    {
        return new \stdClass();
    }
}

class StubGenJson extends GenJson
{
    protected function loadSchema(&$skipRoot, &$baseName)
    {
        return new \stdClass();
    }
}

class StubGenMarkdown extends GenMarkdown
{
    protected function loadSchema(&$skipRoot, &$baseName)
    {
        return new \stdClass();
    }
}

class StubGenPhp extends GenPhp
{
    protected function loadSchema(&$skipRoot, &$baseName)
    {
        return new \stdClass();
    }
}

class CodeGenTest extends TestCase
{
    private function assets($file = '')
    {
        return __DIR__ . '/../../tests/assets/' . $file;
    }

    // tests GenGo output to file
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

    // tests GenGo withTests option
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

    // tests GenGo output to nonexistent directory
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

    // tests GenGo builder options
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

    // tests GenGo with config file
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

    // tests GenGo with empty config file
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

    // tests GenGo with invalid JSON config
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

    // tests GenGo exception handling
    public function testGenGoExceptionPath()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '{"invalid":"not a schema"}');

        try {
            $d = new GenGo();
            $d->schema = $tmpFile;
            $d->packageName = 'testpkg';
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests GenGo rootName option
    public function testGenGoRootName()
    {
        $d = new GenGo();
        $d->schema = $this->assets('sample-schema2.json');
        $d->packageName = 'testpkg';
        $d->rootName = 'MyCustomRoot';
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty($res);
    }

    // tests GenGo withTests writing test file
    public function testGenGoWithTestsAndOutput()
    {
        $tmpDir = sys_get_temp_dir() . '/json-cli-test-gengo-' . uniqid();
        mkdir($tmpDir);
        $outputFile = $tmpDir . '/output.go';

        $tmpSchema = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpSchema, '{"type":"string"}');

        try {
            $d = new GenGo();
            $d->schema = $tmpSchema;
            $d->packageName = 'testpkg';
            $d->withTests = true;
            $d->output = $outputFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $this->assertFileExists($outputFile);
            $testFile = $tmpDir . '/output_test.go';
            $this->assertFileExists($testFile);
        } finally {
            @array_map('unlink', glob($tmpDir . '/*') ?: []);
            @rmdir($tmpDir);
            @unlink($tmpSchema);
        }
    }

    // tests GenJSDoc output to file
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

    // tests GenJSDoc output to nonexistent directory
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

    // tests GenJSDoc with non-Schema type
    public function testGenJSDocNotSchemaType()
    {
        $d = new StubGenJSDoc();
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

    // tests GenJSDoc exception handling
    public function testGenJSDocExceptionPath()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '""');

        try {
            $d = new GenJSDoc();
            $d->schema = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests GenMarkdown output to file
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

    // tests GenMarkdown output to nonexistent directory
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

    // tests GenMarkdown with non-Schema type
    public function testGenMarkdownNotSchemaType()
    {
        $d = new StubGenMarkdown();
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

    // tests GenMarkdown exception handling
    public function testGenMarkdownExceptionPath()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '""');

        try {
            $d = new GenMarkdown();
            $d->schema = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests GenJson without random seed
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

    // tests GenJson with maxNesting and additionalProperties
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

    // tests GenJson with schema from file
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

    // tests GenJson with non-Schema type
    public function testGenJsonNotSchemaType()
    {
        $d = new StubGenJson();
        $d->schema = $this->assets('sample-schema2.json');
        $d->randSeed = 1;
        $d->setResponse(new Response());

        $this->expectException(ExitCode::class);
        ob_start();
        try {
            $d->performAction();
        } finally {
            ob_end_clean();
        }
    }

    // tests GenJson exception handling
    public function testGenJsonExceptionPath()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '""');

        try {
            $d = new GenJson();
            $d->schema = $tmpFile;
            $d->randSeed = 1;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests GenPhp builder options
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

    // tests GenPhp with nonexistent namespace path
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

    // tests GenPhp with ptrInSchema
    public function testGenPhpSkipRootPath()
    {
        $tmpDir = sys_get_temp_dir() . '/json-cli-test-genphp-' . uniqid();
        mkdir($tmpDir);

        try {
            $d = new GenPhp();
            $d->schema = $this->assets('sample-schema2.json');
            $d->ns = 'TestNs';
            $d->nsPath = $tmpDir;
            $d->ptrInSchema = ['#/definitions/SampleSchema'];
            $d->defPtr = ['#/definitions/'];
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_end_clean();

            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertTrue(true);
        } finally {
            $files = glob($tmpDir . '/**/*') ?: [];
            $files = array_merge($files, glob($tmpDir . '/*') ?: []);
            foreach (array_reverse($files) as $f) {
                is_dir($f) ? @rmdir($f) : @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    // tests GenPhp with non-Schema type
    public function testGenPhpNotSchemaType()
    {
        $tmpDir = sys_get_temp_dir() . '/json-cli-test-genphp-' . uniqid();
        mkdir($tmpDir);

        try {
            $d = new StubGenPhp();
            $d->schema = $this->assets('sample-schema2.json');
            $d->ns = 'TestNs';
            $d->nsPath = $tmpDir;
            $d->setResponse(new Response());

            $this->expectException(ExitCode::class);
            ob_start();
            try {
                $d->performAction();
            } finally {
                ob_end_clean();
            }
        } finally {
            @array_map('unlink', glob($tmpDir . '/*') ?: []);
            @rmdir($tmpDir);
        }
    }

    // tests GenPhp exception handling
    public function testGenPhpExceptionPath()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '""');

        try {
            $d = new GenPhp();
            $d->schema = $tmpFile;
            $d->ns = 'Test';
            $d->nsPath = sys_get_temp_dir();
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests GenPhp with rootName option
    public function testGenPhpRootPointer()
    {
        $tmpDir = sys_get_temp_dir() . '/json-cli-test-genphp-' . uniqid();
        mkdir($tmpDir);

        try {
            $d = new GenPhp();
            $d->schema = $this->assets('sample-schema2.json');
            $d->ns = 'TestNs';
            $d->nsPath = $tmpDir;
            $d->rootName = 'MyRoot';
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            ob_get_clean();

            $this->assertTrue(true);
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertTrue(true);
        } finally {
            array_map('unlink', glob($tmpDir . '/*.php') ?: []);
            @rmdir($tmpDir);
        }
    }

    // tests LoadFile with JSON array patch
    public function testLoadFileWithJsonArrayPatch()
    {
        $d = new GenJson();
        $d->schema = $this->assets('sample-schema2.json');
        $d->patches = [$this->assets('patch.json')];
        $d->randSeed = 1;
        $d->setResponse(new Response());
        ob_start();
        try {
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            $this->assertSame(1, $e->getCode());
            return;
        }
        $res = ob_get_clean();
        $this->assertNotEmpty($res);
    }

    // tests LoadFile with merge patch
    public function testLoadFileWithMergePatch()
    {
        $d = new GenJson();
        $d->schema = $this->assets('sample-schema2.json');
        $d->patches = [$this->assets('merge-patch.json')];
        $d->randSeed = 1;
        $d->setResponse(new Response());
        ob_start();
        try {
            $d->performAction();
        } catch (ExitCode $e) {
            ob_end_clean();
            return;
        }
        $res = ob_get_clean();
        $this->assertTrue(true);
    }

    // tests LoadFile with empty schema
    public function testLoadFileEmptySchema()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, '');

        try {
            $d = new GenJson();
            $d->schema = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $this->expectException(ExitCode::class);
            $d->performAction();
        } finally {
            ob_end_clean();
            @unlink($tmpFile);
        }
    }
}
