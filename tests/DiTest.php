<?php
namespace Test\Resta;

use Error;
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;
use Wp\Resta\DI\Container;

class Sample {}
class Sample2 {}
interface SampleInterface {}
class Sample3 implements SampleInterface {}

class DiTest extends TestCase
{
    public function testAutowiring()
    {
        $container = Container::getInstance();
        $container->bind(Sample::class);
        $this->assertEquals(new Sample, $container->get(Sample::class));
    }

    public function testObjectBind()
    {
        $container = Container::getInstance();
        $container->bind(Sample::class, new Sample);
        $this->assertEquals(new Sample, $container->get(Sample::class));
        $this->assertNotSame(new Sample, $container->get(Sample::class));
    }

    public function testProviderBinding()
    {
        $container = Container::getInstance();
        $container->bind(Sample::class, function () {
            return new Sample;
        });
        $this->assertEquals(new Sample, $container->get(Sample::class));
        $this->assertNotSame($container->get(Sample::class), $container->get(Sample::class));
    }

    public function testDoNotResolveOtherType()
    {
        $container = Container::getInstance();
        $container->bind(Sample::class, function () {
            return new Sample2;
        });
        $this->expectException(Exception::class);
        $container->get(Sample::class);
    }

    public function testDoNotResolveClassDoesNotExist()
    {
        $container = Container::getInstance();
        $container->bind(Sample::class, 'DoNotExistClass');
        $this->expectException(Exception::class);
        $container->get(Sample::class);
    }

    public function testCannotBindArray()
    {
        $container = Container::getInstance();
        $this->expectException(TypeError::class);
        $container->bind(Sample::class, ['this', 'is', 'array']); // @phpstan-ignore-line
    }

    public function testOnlyBindInterfaceShouldFail()
    {
        $container = Container::getInstance();
        $container->bind(SampleInterface::class);
        $this->expectException(Error::class);
        $container->get(SampleInterface::class);
    }

    public function testBindInterfaceToImplementation()
    {
        $container = Container::getInstance();
        $container->unbind(SampleInterface::class);
        $container->bind(SampleInterface::class, Sample3::class);
        $this->assertEquals(new Sample3, $container->get(SampleInterface::class));
    }
}
