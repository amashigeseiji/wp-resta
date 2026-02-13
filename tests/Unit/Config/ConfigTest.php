<?php
namespace Test\Resta\Unit\Config;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Config;

class ConfigTest extends TestCase
{
    public function testGetReturnsValueForExistingKey()
    {
        $config = new Config([
            'test_key' => 'test_value'
        ]);

        $this->assertEquals('test_value', $config->get('test_key'));
    }

    public function testGetReturnsNullForNonExistingKey()
    {
        $config = new Config([]);

        $this->assertNull($config->get('non_existing'));
    }

    public function testHasKeyReturnsTrueForExistingKey()
    {
        $config = new Config([
            'existing' => 'value'
        ]);

        $this->assertTrue($config->hasKey('existing'));
    }

    public function testHasKeyReturnsFalseForNonExistingKey()
    {
        $config = new Config([]);

        $this->assertFalse($config->hasKey('non_existing'));
    }

    public function testGetHandlesNestedArrays()
    {
        $config = new Config([
            'routeDirectory' => [
                ['/path', 'Namespace\\', 'api']
            ]
        ]);

        $routes = $config->get('routeDirectory');
        $this->assertIsArray($routes);
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', $routes[0][0]);
    }

    public function testGetHandlesEmptyArrays()
    {
        $config = new Config([
            'hooks' => []
        ]);

        $hooks = $config->get('hooks');
        $this->assertIsArray($hooks);
        $this->assertEmpty($hooks);
    }

    public function testHasKeyDistinguishesBetweenNullAndNonExistent()
    {
        $config = new Config([
            'nullable_key' => null
        ]);

        // null が設定されているキーは存在する
        $this->assertTrue($config->hasKey('nullable_key'));
        $this->assertNull($config->get('nullable_key'));

        // 設定されていないキーは存在しない
        $this->assertFalse($config->hasKey('non_existent'));
        $this->assertNull($config->get('non_existent'));
    }

    public function testGetHandlesVariousDataTypes()
    {
        $config = new Config([
            'string' => 'value',
            'int' => 42,
            'bool' => true,
            'array' => [1, 2, 3],
            'null' => null,
        ]);

        $this->assertIsString($config->get('string'));
        $this->assertIsInt($config->get('int'));
        $this->assertIsBool($config->get('bool'));
        $this->assertIsArray($config->get('array'));
        $this->assertNull($config->get('null'));
    }
}
