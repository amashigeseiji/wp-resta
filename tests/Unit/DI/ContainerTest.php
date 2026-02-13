<?php
namespace Test\Resta\Unit\DI;

use PHPUnit\Framework\TestCase;
use Wp\Resta\DI\Container;
use Brain\Monkey;
use ReflectionClass;

class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();

        // Container をリセット
        $reflection = new ReflectionClass(Container::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        parent::tearDown();
    }

    public function testGetInstanceReturnsSingleton()
    {
        $container1 = Container::getInstance();
        $container2 = Container::getInstance();

        $this->assertSame($container1, $container2);
    }

    public function testBindAndGetSimpleClass()
    {
        $container = Container::getInstance();
        $container->bind(SimpleClass::class);

        $instance = $container->get(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testBindWithDependencyInjection()
    {
        $container = Container::getInstance();

        $instance = $container->get(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->getDependency());
    }

    public function testBindInterfaceToImplementation()
    {
        $container = Container::getInstance();
        $container->bind(TestInterface::class, TestImplementation::class);

        $instance = $container->get(TestInterface::class);

        $this->assertInstanceOf(TestImplementation::class, $instance);
    }

    public function testBindWithCallable()
    {
        $container = Container::getInstance();
        $container->bind(SimpleClass::class, function() {
            $obj = new SimpleClass();
            $obj->value = 'custom';
            return $obj;
        });

        $instance = $container->get(SimpleClass::class);

        $this->assertEquals('custom', $instance->value);
    }

    public function testGetThrowsExceptionForUndefinedClass()
    {
        $this->expectException(\Exception::class);

        $container = Container::getInstance();
        $container->get(NonExistentInterface::class);
    }

    public function testBindReturnsSameInstanceOnMultipleCalls()
    {
        $container = Container::getInstance();
        $container->bind(SimpleClass::class);

        $instance1 = $container->get(SimpleClass::class);
        $instance2 = $container->get(SimpleClass::class);

        // 同じインスタンスが返される（シングルトン的挙動）
        $this->assertSame($instance1, $instance2);
    }

    public function testCallableBindReturnsNewInstanceOnEachCall()
    {
        $container = Container::getInstance();
        $container->bind(SimpleClass::class, function() {
            return new SimpleClass();
        });

        $instance1 = $container->get(SimpleClass::class);
        $instance2 = $container->get(SimpleClass::class);

        // Callable バインドの場合、毎回異なるインスタンスが返される
        $this->assertNotSame($instance1, $instance2);
    }

    public function testCallableReturningWrongTypeThrowsException()
    {
        $this->expectException(\Exception::class);

        $container = Container::getInstance();
        $container->bind(SimpleClass::class, function() {
            return new AnotherClass();
        });

        $container->get(SimpleClass::class);
    }

    public function testBindingNonExistentClassNameThrowsException()
    {
        $this->expectException(\Exception::class);

        $container = Container::getInstance();
        $container->bind(SimpleClass::class, 'NonExistentClassName');

        $container->get(SimpleClass::class);
    }

    public function testBindingArrayThrowsTypeError()
    {
        $this->expectException(\TypeError::class);

        $container = Container::getInstance();
        $container->bind(SimpleClass::class, ['this', 'is', 'array']); // @phpstan-ignore-line
    }

    public function testBindingOnlyInterfaceThrowsError()
    {
        $this->expectException(\Error::class);

        $container = Container::getInstance();
        $container->bind(OnlyInterface::class);

        $container->get(OnlyInterface::class);
    }

    public function testUnbindRemovesBinding()
    {
        $container = Container::getInstance();
        $container->bind(TestInterface::class, TestImplementation::class);

        // unbind してから別の実装をバインド
        $container->unbind(TestInterface::class);
        $container->bind(TestInterface::class, AnotherImplementation::class);

        $instance = $container->get(TestInterface::class);

        $this->assertInstanceOf(AnotherImplementation::class, $instance);
    }
}

// テスト用クラス
class SimpleClass
{
    public $value = 'default';
}

class AnotherClass
{
    public $value = 'another';
}

class ClassWithDependency
{
    private $dependency;

    public function __construct(SimpleClass $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency()
    {
        return $this->dependency;
    }
}

interface TestInterface {}
class TestImplementation implements TestInterface {}
class AnotherImplementation implements TestInterface {}
interface NonExistentInterface {}
interface OnlyInterface {}
