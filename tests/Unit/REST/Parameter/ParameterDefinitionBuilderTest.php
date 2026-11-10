<?php
namespace Test\Resta\Unit\REST\Parameter;

use LogicException;
use PHPUnit\Framework\TestCase;
use Test\Resta\Fixtures\Parameter\Sort;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Callback;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Parameter\ParameterDefinition;
use Wp\Resta\REST\Parameter\ParameterDefinitionBuilder;
use Wp\Resta\REST\Parameter\RouteParameters;

class ParameterDefinitionBuilderTest extends TestCase
{
    /**
     * @param array<string, string|array<string, mixed>> $urlParams
     */
    private function build(string $route, array $urlParams, object $owner): RouteParameters
    {
        $callback = method_exists($owner, 'callback') ? Callback::fromMethod($owner, 'callback') : Callback::returning(null);
        return (new ParameterDefinitionBuilder())->build($route, $urlParams, $callback, 'TestRoute');
    }

    // --- URL_PARAMS からの導出（従来の getArgs() と同じ形を保つ） ---

    public function testUrlParamsShorthandsProduceSameCoreArgsAsBefore(): void
    {
        $params = $this->build('item/[id]', [
            'id' => 'integer',
            'name' => '?string',
            'slug' => '[a-z]+',
        ], new class {});

        $this->assertSame([
            'id' => ['type' => 'integer', 'required' => true, 'regex' => '\d+', 'description' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'regex' => null, 'description' => 'name'],
            'slug' => ['type' => 'string', 'required' => true, 'regex' => '[a-z]+', 'description' => 'slug', 'pattern' => '^(?:[a-z]+)$'],
        ], $params->toCoreArgs());
        $this->assertTrue($params->get('id')->isPath());
        $this->assertFalse($params->get('name')->isPath());
    }

    public function testUrlParamsArrayDefinitionIsPassedThroughWithPattern(): void
    {
        $params = $this->build('x', [
            'order' => ['type' => 'string', 'required' => false, 'regex' => '(asc|desc)', 'description' => '並び順', 'enum' => ['asc', 'desc']],
            'free' => ['type' => 'string'],
        ], new class {});

        $this->assertSame(
            ['type' => 'string', 'required' => false, 'regex' => '(asc|desc)', 'description' => '並び順', 'enum' => ['asc', 'desc'], 'pattern' => '^(?:(asc|desc))$'],
            $params->get('order')->toCoreArg(),
        );
        $this->assertSame(['asc', 'desc'], $params->get('order')->enum);
        $this->assertSame(['type' => 'string'], $params->get('free')->toCoreArg(), 'regex が無ければ pattern も付かない');
        $this->assertFalse($params->get('free')->required);
        $this->assertNull($params->get('free')->regex);
    }

    // --- callback のシグニチャからの導出 ---

    public function testBuiltinArgumentsBecomeDeclarations(): void
    {
        $params = $this->build('item/[id]', [], new class {
            public function callback(int $id, string $q, float $ratio = 1.5, bool $flag = false): void {}
        });

        $id = $params->get('id');
        $this->assertSame('integer', $id->type);
        $this->assertSame(ParameterDefinition::PATH, $id->location);
        $this->assertTrue($id->required);
        $this->assertSame('\d+', $id->regex);

        $q = $params->get('q');
        $this->assertSame('string', $q->type);
        $this->assertSame(ParameterDefinition::QUERY, $q->location);
        $this->assertTrue($q->required);
        $this->assertNull($q->regex, 'string は制約なし');

        $ratio = $params->get('ratio');
        $this->assertSame('number', $ratio->type);
        $this->assertFalse($ratio->required);
        $this->assertTrue($ratio->hasDefault);
        $this->assertSame(1.5, $ratio->default);

        $this->assertSame('boolean', $params->get('flag')->type);
        $this->assertSame(['type' => 'integer', 'required' => true, 'regex' => '\d+', 'description' => 'id'], $id->toCoreArg());
    }

    public function testClassTypedArgumentsAreNotParameters(): void
    {
        $params = $this->build('x', [], new class {
            public function callback(int $id, RestaRequestInterface $request, \stdClass $service): void {}
        });

        $this->assertSame(['id'], array_keys($params->all()));
    }

    public function testUrlParamsOverrideSignatureByName(): void
    {
        // 移行期は URL_PARAMS が優先。string $id と 'integer' が食い違っても URL_PARAMS の宣言で登録する
        $params = $this->build('item/[id]', ['id' => 'integer'], new class {
            public function callback(string $id, int $page = 1): void {}
        });

        $this->assertSame('integer', $params->get('id')->type);
        $this->assertSame('integer', $params->get('page')->type);
        $this->assertSame(['id', 'page'], array_keys($params->all()));
    }

    public function testBackedEnumBecomesEnumDeclaration(): void
    {
        $params = $this->build('x', [], new class {
            public function callback(Sort $sort = Sort::Asc): void {}
        });

        $sort = $params->get('sort');
        $this->assertSame('string', $sort->type);
        $this->assertSame(['asc', 'desc'], $sort->enum);
        $this->assertSame(Sort::class, $sort->bindType);
        $this->assertSame('asc', $sort->default);
        $this->assertSame(['asc', 'desc'], $sort->toCoreArg()['enum']);
    }

    public function testArrayArgumentReadsItemTypeFromDocblock(): void
    {
        $params = $this->build('x', [], new class {
            /**
             * 一覧を返す
             *
             * @param string[] $tags タグの一覧
             * @param array<int> $ids
             * @param list<bool> $flags
             * @param array<string, float> $weights
             * @param array $raw
             * @param array{id: int}[] $shapes
             * @param int $page ページ番号
             * @return array<string, mixed>
             */
            public function callback(array $tags = [], array $ids = [], array $flags = [], array $weights = [], array $raw = [], array $shapes = [], int $page = 1): void {}
        });

        $this->assertSame(['type' => 'string'], $params->get('tags')->items);
        $this->assertSame('タグの一覧', $params->get('tags')->description);
        $this->assertSame(['type' => 'integer'], $params->get('ids')->items);
        $this->assertSame(['type' => 'boolean'], $params->get('flags')->items);
        $this->assertSame(['type' => 'number'], $params->get('weights')->items, 'array<K, T> は T を読む');
        $this->assertSame([], $params->get('raw')->items, '要素型が読めなければ任意');
        $this->assertSame([], $params->get('shapes')->items, 'array shape の要素は URL から来ないので任意');
        $this->assertSame('ページ番号', $params->get('page')->description, '配列以外の引数も説明文は読む');
        $this->assertSame('ids', $params->get('ids')->description, '説明が無ければ名前');
    }

    public function testSingleLineDocblockIsRead(): void
    {
        $params = $this->build('x', [], new class {
            /** @param string[] $tags タグ */
            public function callback(array $tags = []): void {}
        });

        $this->assertSame(['type' => 'string'], $params->get('tags')->items);
        $this->assertSame('タグ', $params->get('tags')->description);
    }

    public function testParamAttributeSuppliesConstraints(): void
    {
        $params = $this->build('x', [], new class {
            public function callback(
                #[Param(pattern: '[a-z/]+', description: 'スラッシュ区切りのパス')] ?string $path = null,
                #[Param(min: 1, max: 100)] int $per_page = 10,
            ): void {}
        });

        $path = $params->get('path');
        $this->assertSame('[a-z/]+', $path->regex);
        $this->assertSame('スラッシュ区切りのパス', $path->description);
        $this->assertSame('^(?:[a-z/]+)$', $path->toCoreArg()['pattern']);
        $this->assertFalse($path->required);

        $perPage = $params->get('per_page');
        $this->assertSame(1, $perPage->minimum);
        $this->assertSame(100, $perPage->maximum);
        $this->assertSame(1, $perPage->toCoreArg()['minimum']);
        $this->assertSame(100, $perPage->toCoreArg()['maximum']);
    }

    // --- ROUTE との突き合わせ ---

    public function testPathRegexReplacesOnlyPathParameters(): void
    {
        $params = $this->build('post/[id]/[slug]', ['slug' => '[a-z-]+'], new class {
            public function callback(int $id, string $slug, string $q = ''): void {}
        });

        $this->assertSame('post/(?P<id>\d+)/(?P<slug>[a-z-]+)', $params->pathRegex('post/[id]/[slug]'));
        $this->assertSame(['slug', 'id'], array_keys($params->path()), 'URL_PARAMS の宣言が先、シグニチャ由来が後（getArgs() の順序を保つ）');
        $this->assertSame(['q'], array_keys($params->query()));
    }

    public function testUnconstrainedPathParameterMatchesUpToNextSlash(): void
    {
        $params = $this->build('hello/[name]', [], new class {
            public function callback(string $name): void {}
        });

        $this->assertSame('hello/(?P<name>[^/]+)', $params->pathRegex('hello/[name]'));
    }

    public function testRouteSegmentWithoutDeclarationIsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ROUTE has [id] but no parameter named "id"');

        $this->build('post/[id]', [], new class {
            public function callback(): void {}
        });
    }

    public function testPathParameterWithDefaultIsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('path parameter and cannot have a default value');

        $this->build('post/[id]', [], new class {
            public function callback(int $id = 0): void {}
        });
    }

    public function testPathParameterWithDefaultIsLogicExceptionEvenWhenUrlParamsDeclaresIt(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('path parameter and cannot have a default value');

        $this->build('post/[id]', ['id' => 'integer'], new class {
            public function callback(int $id = 0): void {}
        });
    }

    public function testUnionTypedArgumentIsLogicException(): void
    {
        $this->expectException(LogicException::class);

        $this->build('x', [], new class {
            public function callback(int|string $id): void {}
        });
    }

    public function testVariadicArgumentIsLogicException(): void
    {
        $this->expectException(LogicException::class);

        $this->build('x', [], new class {
            public function callback(string ...$names): void {}
        });
    }

    public function testUntypedArgumentIsLogicException(): void
    {
        $this->expectException(LogicException::class);

        $this->build('x', [], new class {
            public function callback($id): void {}
        });
    }

    public function testToOpenApiParameterMatchesPreviousShape(): void
    {
        $params = $this->build('item/[id]', ['id' => 'integer'], new class {
            public function callback(int $id, string $q = ''): void {}
        });

        $this->assertSame([
            'name' => 'id',
            'required' => true,
            'description' => 'id',
            'in' => 'path',
            'schema' => ['type' => 'integer'],
        ], $params->get('id')->toOpenApiParameter());
        $this->assertSame('query', $params->get('q')->toOpenApiParameter()['in']);
    }

    public function testToOpenApiParameterIncludesItemsForArray(): void
    {
        $params = $this->build('x', [], new class {
            /** @param string[] $tags */
            public function callback(array $tags = [], array $raw = []): void {}
        });

        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $params->get('tags')->toOpenApiParameter()['schema']);
        $this->assertSame(['type' => 'array', 'items' => []], $params->get('raw')->toOpenApiParameter()['schema'], '要素型が読めなくても items は出す');
    }
}
