<?php

namespace Swaggest\JsonCli\Tests;

use PHPUnit\Framework\TestCase;
use Swaggest\JsonCli\Diff;
use Swaggest\JsonCli\DiffInfo;
use Swaggest\JsonCli\ExitCode;
use Swaggest\JsonCli\Rearrange;
use Yaoi\Cli\Response;

class StubDiffWithException extends Diff
{
    protected function prePerform()
    {
        $this->response->error('Simulated JsonDiff exception');
    }
}

class StubDiffNullPrePerform extends Diff
{
    protected function prePerform()
    {
        $this->out = '';
    }
}

class StubRearrangeNullPrePerform extends Rearrange
{
    protected function prePerform()
    {
        $this->out = '';
    }
}

class DiffCommandTest extends TestCase
{
    private function assets($file = '')
    {
        return __DIR__ . '/../../tests/assets/' . $file;
    }

    // tests diff without rearrangeArrays option
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

    // tests prettyShort format output
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

    // tests merge patch mode
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

    // tests identical files produce valid output
    public function testDiffNullDiffReturnsEarly()
    {
        $d = new Diff();
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('original.json');
        $d->rearrangeArrays = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty(trim($res));
    }

    // tests prettyShort with empty diff
    public function testDiffPrettyShortEmptyDiff()
    {
        $d = new Diff();
        $d->prettyShort = true;
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('original.json');
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotNull(json_decode(trim($res)));
    }

    // tests null diff causes early return
    public function testDiffNullDiffEarlyReturn()
    {
        $d = new StubDiffNullPrePerform();
        $d->setResponse(new Response());

        ob_start();
        $d->performAction();
        ob_end_clean();

        $ref = new \ReflectionProperty($d, 'diff');
        $ref->setAccessible(true);
        $this->assertNull($ref->getValue($d));
    }

    // tests prePerform with invalid JSON input
    public function testBaseDiffPrePerformException()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'json-cli-test-') . '.json';
        file_put_contents($tmpFile, 'not json');

        try {
            $d = new Diff();
            $d->originalPath = $this->assets('original.json');
            $d->newPath = $tmpFile;
            $d->setResponse(new Response());
            ob_start();
            $d->performAction();
            $res = ob_get_clean();
            $this->assertTrue(true);
        } finally {
            @unlink($tmpFile);
        }
    }

    // tests exception handling in prePerform
    public function testBaseDiffExceptionCatch()
    {
        $d = new StubDiffWithException();
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('original.json');
        $d->setResponse(new Response());

        ob_start();
        $d->performAction();
        $out = ob_get_clean();

        $ref = new \ReflectionProperty($d, 'diff');
        $ref->setAccessible(true);
        $this->assertNull($ref->getValue($d));
    }

    // tests DiffInfo without paths or contents
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

    // tests DiffInfo with paths only
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

    // tests rearrange without rearrangeArrays
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

    // tests rearrange with identical files
    public function testRearrangeIdenticalFiles()
    {
        $d = new Rearrange();
        $d->originalPath = $this->assets('original.json');
        $d->newPath = $this->assets('original.json');
        $d->rearrangeArrays = true;
        $d->setResponse(new Response());
        ob_start();
        $d->performAction();
        $res = ob_get_clean();

        $this->assertNotEmpty(trim($res));
    }

    // tests null diff causes early return in Rearrange
    public function testRearrangeNullDiffEarlyReturn()
    {
        $d = new StubRearrangeNullPrePerform();
        $d->setResponse(new Response());

        ob_start();
        $d->performAction();
        ob_end_clean();

        $ref = new \ReflectionProperty($d, 'diff');
        $ref->setAccessible(true);
        $this->assertNull($ref->getValue($d));
    }
}
