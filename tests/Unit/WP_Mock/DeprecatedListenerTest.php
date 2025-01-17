<?php

namespace WP_Mock\Tests\Unit\WP_Mock;

use Exception;
use Mockery;
use PHPUnit\Framework\RiskyTest;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WP_Mock\DeprecatedListener;
use WP_Mock\Tests\WP_MockTestCase;

/**
 * @covers \WP_Mock\DeprecatedListener
 */
class DeprecatedListenerTest extends WP_MockTestCase
{
    /** @var DeprecatedListener */
    protected $object;

    protected function setUp(): void
    {
        $this->object = new DeprecatedListener();
    }

    public function tearDown(): void
    {
        $this->object->reset();
    }

    /**
     * @covers \WP_Mock\DeprecatedListener::logDeprecatedCall()
     *
     * @return void
     */
    public function testLogDeprecatedCall()
    {
        $method = 'Foobar::asdf' . rand(0, 9);
        $args   = array( rand(10, 99) );
        $this->object->logDeprecatedCall($method, $args);

        $this->assertEquals(array( array( $method, $args ) ), $this->getCalls($this->object));
    }

    /**
     * @covers \WP_Mock\DeprecatedListener::reset()
     *
     * @return void
     */
    public function testReset()
    {
        $this->object->logDeprecatedCall('Asdf', array( 'foobar' ));
        $this->object->reset();

        $this->assertEquals(array(), $this->getCalls($this->object));
    }

    /**
     * @covers \WP_Mock\DeprecatedListener::checkCalls()
     *
     * @return void
     */
    public function testCheckCallsNoCalls()
    {
        $testResult = new \PHPUnit\Framework\TestResult();
        $result     = Mockery::mock($testResult);
        $result->shouldReceive('addFailure')->never();
        /** @var \\PHPUnit\Framework\TestResult $result */
        $this->object->setTestResult($result);

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $this->assertNull($this->object->checkCalls());
    }

    /**
     * @covers \WP_Mock\DeprecatedListener::checkCalls()
     *
     * @return void
     */
    public function testCheckCalls_scalar_only()
    {
        $this->object->logDeprecatedCall('FooBar::bazBat', array( 'string', true, 42 ));
        $this->object->setTestName('TestName');
        $testCase = Mockery::mock('\PHPUnit\Framework\TestCase');
        /** @var TestCase $testCase */
        $this->object->setTestCase($testCase);
        $testResult = new \PHPUnit\Framework\TestResult();
        $result     = Mockery::mock($testResult);
        $result->shouldReceive('addFailure')
            ->once()
            ->andReturnUsing(function ($case, $exception, $int) use ($testCase) {
                $int = (int) $int; // It's coming as 0.0
                \PHPUnit\Framework\Assert::assertSame($testCase, $case);
                \PHPUnit\Framework\Assert::assertTrue($exception instanceof RiskyTestError);
                $message = <<<EOT
Deprecated WP Mock calls inside TestName:
  FooBar::bazBat ["string",true,42]
EOT;
                \PHPUnit\Framework\Assert::assertEquals($message, $exception->getMessage());
                \PHPUnit\Framework\Assert::assertTrue(0 === $int);
            });
        /** @var \\PHPUnit\Framework\TestResult $result */
        $this->object->setTestResult($result);

        $this->object->checkCalls();
    }

    /**
     * @covers \WP_Mock\DeprecatedListener::checkCalls()
     *
     * @return void
     * @throws Exception
     */
    public function testCheckCalls_non_scalars()
    {
        $callback1 = function () {
        };
        $object1   = Mockery::mock('WP_Query');
        $range     = rand(5, 10);
        $resource  = fopen('php://temp', 'r');
        $this->object->logDeprecatedCall('BazBat::fooBar', array( $callback1 ));
        $this->object->logDeprecatedCall('BazBat::fooBar', array( $object1 ));
        $this->object->logDeprecatedCall('LongerClassName::callback', array( array( $object1, 'shouldReceive' ) ));
        $this->object->logDeprecatedCall('BazBat::fooBar', array( range(1, $range), $resource ));
        $this->object->setTestName('OtherTest');
        $testCase = Mockery::mock('\PHPUnit\Framework\TestCase');
        /** @var TestCase $testCase */
        $this->object->setTestCase($testCase);
        $testResult = new \PHPUnit\Framework\TestResult();
        $result     = Mockery::mock($testResult);
        ;
        $testClosure = function ($case, $exception, $int) use ($testCase, $callback1, $object1, $range) {
            $int = (int) $int; // It's coming as 0.0
            $callback1 = get_class($callback1) . ':' . spl_object_hash($callback1);
            $object1   = get_class($object1) . ':' . spl_object_hash($object1);
            \PHPUnit\Framework\Assert::assertSame($testCase, $case);
            \PHPUnit\Framework\Assert::assertTrue($exception instanceof RiskyTestError);
            $message = <<<EOT
Deprecated WP Mock calls inside OtherTest:
  BazBat::fooBar            ["<$callback1>"]
                            ["<$object1>"]
                            ["Array([$range] ...)","Resource"]
  LongerClassName::callback ["[<$object1>,shouldReceive]"]
EOT;
            \PHPUnit\Framework\Assert::assertEquals($message, $exception->getMessage());
            \PHPUnit\Framework\Assert::assertTrue(0 === $int);
        };
        $result->shouldReceive('addFailure')
            ->once()
            ->andReturnUsing($testClosure);
        /** @var \\PHPUnit\Framework\TestResult $result */
        $this->object->setTestResult($result);

        try {
            $this->object->checkCalls();
        } catch (\Exception $e) {
            fclose($resource);
            throw $e;
        }
        fclose($resource);
    }

    protected function getCalls($listener)
    {
        $prop = new ReflectionProperty($listener, 'calls');
        $prop->setAccessible(true);

        return $prop->getValue($listener);
    }
}
