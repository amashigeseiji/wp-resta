<?php
namespace Test\Resta\Unit\REST\Parameter;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\Resta\Fixtures\Parameter\Sort;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Callback;
use Wp\Resta\REST\Exception\InvalidParameterException;
use Wp\Resta\REST\Exception\MissingParameterException;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Parameter\ParameterDefinitionBuilder;
use Wp\Resta\REST\Parameter\RouteParameters;

/**
 * RouteParameters::validate() の検証と型変換。規則は WP コアの rest_sanitize_value_from_schema() に寄せる
 */
class RouteParametersValidateTest extends TestCase
{
    /** @param array<string, string|array<string, mixed>> $urlParams */
    private function params(string $route, array $urlParams, object $owner): RouteParameters
    {
        return (new ParameterDefinitionBuilder())->build($route, $urlParams, Callback::fromMethod($owner, 'callback'), 'TestRoute');
    }

    /** @param array<string, mixed> $values */
    private function request(array $values): RestaRequestInterface
    {
        return new class($values) implements RestaRequestInterface {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values) {}
            public function getMethod(): string
            {
                return 'GET';
            }
            public function getHeader(string $name): ?string
            {
                return null;
            }
            public function getUrlParam(string $name): mixed
            {
                return $this->values[$name] ?? null;
            }
        };
    }

    private function typed(): RouteParameters
    {
        return $this->params('x', [], new class {
            /** @param string[] $tags */
            public function callback(
                int $id = 0,
                float $ratio = 0.0,
                bool $flag = false,
                string $q = '',
                array $tags = [],
                Sort $sort = Sort::Asc,
                #[Param(min: 1, max: 100)] int $per_page = 10,
                #[Param(pattern: '[a-z]+')] string $slug = '',
            ): void {}
        });
    }

    public function testConvertsStringsToDeclaredTypes(): void
    {
        $bound = $this->typed()->validate($this->request([
            'id' => '42', 'ratio' => '1.5', 'flag' => 'true', 'q' => 'hello', 'tags' => 'a, b', 'sort' => 'desc', 'per_page' => '50', 'slug' => 'abc',
        ]));

        $this->assertSame(42, $bound->get('id'));
        $this->assertSame(1.5, $bound->get('ratio'));
        $this->assertTrue($bound->get('flag'));
        $this->assertSame('hello', $bound->get('q'));
        $this->assertSame(['a', 'b'], $bound->get('tags'), 'カンマ区切りは配列になる');
        $this->assertSame(Sort::Desc, $bound->get('sort'), 'backed enum はインスタンスに束縛される');
        $this->assertSame(50, $bound->get('per_page'));
        $this->assertSame('abc', $bound->get('slug'));
    }

    public function testAcceptsAlreadyTypedValues(): void
    {
        // JSON ボディなどで型付きの値が来ても同じ結果になる
        $bound = $this->typed()->validate($this->request(['id' => 42, 'flag' => false, 'tags' => ['x', 'y'], 'ratio' => 2]));

        $this->assertSame(42, $bound->get('id'));
        $this->assertFalse($bound->get('flag'));
        $this->assertSame(['x', 'y'], $bound->get('tags'));
        $this->assertSame(2.0, $bound->get('ratio'));
    }

    public function testOmittedOptionalParameterTakesDeclaredDefault(): void
    {
        $bound = $this->typed()->validate($this->request([]));

        $this->assertSame(0, $bound->get('id'));
        $this->assertSame(Sort::Asc, $bound->get('sort'), 'enum の default も束縛時にインスタンスになる');
        $this->assertSame(10, $bound->get('per_page'));
    }

    public function testNullableEnumDefaultsToNullWhenOmitted(): void
    {
        $params = $this->params('x', [], new class {
            public function callback(?Sort $sort = null): void {}
        });

        $bound = $params->validate($this->request([]));
        $this->assertTrue($bound->has('sort'));
        $this->assertNull($bound->get('sort'), 'default の null は enum に変換せずそのまま束縛する');

        $this->assertSame(Sort::Desc, $params->validate($this->request(['sort' => 'desc']))->get('sort'));
    }

    public function testOmittedOptionalParameterWithoutDefaultIsAbsent(): void
    {
        $params = $this->params('x', ['name' => '?string'], new class {
            public function callback(string $name = 'anon'): void {}
        });

        $bound = $params->validate($this->request([]));

        $this->assertFalse($bound->has('name'), 'URL_PARAMS 由来の宣言には default が無いので、callback 側のデフォルト値に任せる');
    }

    public function testMissingRequiredParameterIs400(): void
    {
        $params = $this->params('item/[id]', [], new class {
            public function callback(int $id): void {}
        });

        $this->expectException(MissingParameterException::class);
        $params->validate($this->request([]));
    }

    public function testValidatesDeclaredParameterEvenIfCallbackDoesNotReceiveIt(): void
    {
        // PR #89 の Copilot 指摘: 宣言の検証は callback の引数にあるかどうかに関係なく行う
        $params = $this->params('x', ['order' => ['type' => 'string', 'regex' => '(asc|desc)']], new class {
            public function callback(): void {}
        });

        $this->expectException(InvalidParameterException::class);
        $params->validate($this->request(['order' => 'zzz']));
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidValues(): array
    {
        return [
            'integer に文字列' => ['id', 'abc'],
            'integer に小数' => ['id', '1.5'],
            'integer に配列' => ['id', ['1']],
            'number に文字列' => ['ratio', 'x'],
            'boolean に yes' => ['flag', 'yes'],
            'string に配列' => ['q', ['a']],
            'enum 外' => ['sort', 'sideways'],
            'minimum 未満' => ['per_page', '0'],
            'maximum 超過' => ['per_page', '101'],
            'pattern 不一致' => ['slug', 'ABC'],
            '配列の要素が配列' => ['tags', [['nested']]],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueIs400(string $name, mixed $value): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->typed()->validate($this->request([$name => $value]));
    }

    public function testBooleanAcceptsCoreSet(): void
    {
        foreach (['true' => true, '1' => true, 'false' => false, '0' => false] as $raw => $expected) {
            $this->assertSame($expected, $this->typed()->validate($this->request(['flag' => (string)$raw]))->get('flag'), $raw);
        }
    }

    public function testArrayItemsAreConvertedToItemType(): void
    {
        $params = $this->params('x', [], new class {
            /** @param int[] $ids */
            public function callback(array $ids = []): void {}
        });

        $this->assertSame([1, 2], $params->validate($this->request(['ids' => '1,2']))->get('ids'));

        $this->expectException(InvalidParameterException::class);
        $params->validate($this->request(['ids' => '1,x']));
    }

    public function testEmptyStringIsAValueNotAnOmission(): void
    {
        $params = $this->params('x', ['name' => '?string', 'kind' => ['type' => 'string', 'required' => false, 'regex' => '(a|b)']], new class {
            public function callback(string $name = 'default', string $kind = 'a'): void {}
        });

        $this->assertSame('', $params->validate($this->request(['name' => '']))->get('name'));

        $this->expectException(InvalidParameterException::class);
        $params->validate($this->request(['kind' => '']));
    }

    public function testInvalidRegexIsLogicExceptionNot400(): void
    {
        $params = $this->params('x', ['id' => ['type' => 'string', 'required' => true, 'regex' => '[']], new class {
            public function callback(string $id): void {}
        });

        set_error_handler(fn() => true, E_WARNING);
        try {
            $this->expectException(LogicException::class);
            $params->validate($this->request(['id' => 'abc']));
        } finally {
            restore_error_handler();
        }
    }
}
