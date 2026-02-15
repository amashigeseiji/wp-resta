<?php
namespace Test\Resta\Unit\Hooks;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;
use Wp\Resta\Hooks\Enum\RestApiHook;

class AttributesTest extends TestCase
{
    public function testAddFilterHasCorrectDefaults()
    {
        $attribute = new AddFilter('test_hook');

        $this->assertEquals('test_hook', $attribute->hook);
        $this->assertEquals(10, $attribute->priority);
        $this->assertEquals(1, $attribute->acceptedArgs);
    }

    public function testAddFilterAcceptsCustomValues()
    {
        $attribute = new AddFilter('custom_hook', priority: 20, acceptedArgs: 3);

        $this->assertEquals('custom_hook', $attribute->hook);
        $this->assertEquals(20, $attribute->priority);
        $this->assertEquals(3, $attribute->acceptedArgs);
    }

    public function testAddActionHasCorrectDefaults()
    {
        $attribute = new AddAction('test_action');

        $this->assertEquals('test_action', $attribute->hook);
        $this->assertEquals(10, $attribute->priority);
        $this->assertEquals(1, $attribute->acceptedArgs);
    }

    public function testAddActionAcceptsCustomValues()
    {
        $attribute = new AddAction('custom_action', priority: 15, acceptedArgs: 2);

        $this->assertEquals('custom_action', $attribute->hook);
        $this->assertEquals(15, $attribute->priority);
        $this->assertEquals(2, $attribute->acceptedArgs);
    }

    public function testAttributesAreReadonly()
    {
        $this->expectException(\Error::class);

        $attribute = new AddFilter('test');
        $attribute->hook = 'modified'; // readonly なので例外
    }

    public function testAddFilterAndAddActionHaveSameStructure()
    {
        $filter = new AddFilter('hook', priority: 20, acceptedArgs: 2);
        $action = new AddAction('hook', priority: 20, acceptedArgs: 2);

        $this->assertEquals($filter->hook, $action->hook);
        $this->assertEquals($filter->priority, $action->priority);
        $this->assertEquals($filter->acceptedArgs, $action->acceptedArgs);
    }

    public function testAddFilterAcceptsEnum()
    {
        $attribute = new AddFilter(RestApiHook::API_INIT, priority: 20, acceptedArgs: 2);

        $this->assertInstanceOf(\BackedEnum::class, $attribute->hook);
        $this->assertEquals(RestApiHook::API_INIT, $attribute->hook);
        $this->assertEquals('rest_api_init', $attribute->hook->value);
        $this->assertEquals(20, $attribute->priority);
        $this->assertEquals(2, $attribute->acceptedArgs);
    }

    public function testAddActionAcceptsEnum()
    {
        $attribute = new AddAction(RestApiHook::PRE_DISPATCH, priority: 15, acceptedArgs: 3);

        $this->assertInstanceOf(\BackedEnum::class, $attribute->hook);
        $this->assertEquals(RestApiHook::PRE_DISPATCH, $attribute->hook);
        $this->assertEquals('rest_pre_dispatch', $attribute->hook->value);
        $this->assertEquals(15, $attribute->priority);
        $this->assertEquals(3, $attribute->acceptedArgs);
    }

    public function testAddFilterGetHookNameWithString()
    {
        $attribute = new AddFilter('test_hook');

        $this->assertEquals('test_hook', $attribute->getHookName());
    }

    public function testAddFilterGetHookNameWithEnum()
    {
        $attribute = new AddFilter(RestApiHook::POST_DISPATCH);

        $this->assertEquals('rest_post_dispatch', $attribute->getHookName());
    }

    public function testAddActionGetHookNameWithString()
    {
        $attribute = new AddAction('test_action');

        $this->assertEquals('test_action', $attribute->getHookName());
    }

    public function testAddActionGetHookNameWithEnum()
    {
        $attribute = new AddAction(RestApiHook::AUTHENTICATION_ERRORS);

        $this->assertEquals('rest_authentication_errors', $attribute->getHookName());
    }
}
