<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\App;
use Swaggest\JsonCli\Apply;
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
use Swaggest\JsonCli\SchemaResolver;
use Swaggest\JsonCli\ValidateSchema;
use Yaoi\Cli\Response;

class DefinitionTest extends TestCase
{
    private function assets($file = '')
    {
        return __DIR__ . '/../../tests/assets/' . $file;
    }

    private function clearDefinitionCache()
    {
        $ref = new \ReflectionProperty(\Yaoi\Command::class, 'definitions');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    // tests setUpDefinition for all command classes
    public function testAllDefinitions()
    {
        $this->clearDefinitionCache();

        $classes = [
            App::class,
            Apply::class,
            Diff::class,
            DiffInfo::class,
            Rearrange::class,
            PrettyPrint::class,
            Minify::class,
            Replace::class,
            Resolve::class,
            ResolvePos::class,
            ValidateSchema::class,
            GenGo::class,
            GenPhp::class,
            GenJSDoc::class,
            GenJson::class,
            GenMarkdown::class,
            BuildSchema::class,
        ];

        foreach ($classes as $class) {
            $def = $class::definition();
            $this->assertNotNull($def, "Definition for $class should not be null");
        }
    }

    // tests SchemaResolver::schema() return value
    public function testSchemaResolverSchema()
    {
        $schema = SchemaResolver::schema();
        $this->assertNotNull($schema);
    }

    // tests SchemaResolver::setUpProperties directly
    public function testSchemaResolverSetUpProperties()
    {
        $properties = new \Swaggest\JsonSchema\Constraint\Properties();
        $schema = new \Swaggest\JsonSchema\Schema();
        SchemaResolver::setUpProperties($properties, $schema);

        $this->assertNotNull($properties->schemaData);
        $this->assertNotNull($properties->schemaFiles);
    }

    // tests PositionResolver no-op interface methods
    public function testPositionResolverDirectCalls()
    {
        $pr = new \Swaggest\JsonCli\FilePosition\PositionResolver();
        $pr->startDocument();
        $pr->endDocument();
        $pr->whitespace(' ');
        $this->assertTrue(true);
    }

    // tests PathState default property values
    public function testPathStateProperties()
    {
        $ps = new \Swaggest\JsonCli\FilePosition\PathState();
        $this->assertSame('', $ps->path ?? '');
        $this->assertFalse($ps->isArray);
        $this->assertFalse($ps->isKey);
        $this->assertSame(0, $ps->arrayIndex);
    }

    // tests ResolverMux returns false with no resolvers
    public function testResolverMuxReturnsFalse()
    {
        $mux = new \Swaggest\JsonCli\JsonSchema\ResolverMux();
        $mux->resolvers = [];
        $result = $mux->getSchemaData('http://example.com/nonexistent');
        $this->assertFalse($result);
    }
}
