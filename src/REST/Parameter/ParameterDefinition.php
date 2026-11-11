<?php
namespace Wp\Resta\REST\Parameter;

/**
 * URL パラメータ 1 つ分の宣言。callback のシグニチャと URL_PARAMS から導出され、リクエストとは無関係。
 * 登録（register_rest_route の args）、ルート正規表現、OpenAPI、検証はすべてここから導く。
 */
final class ParameterDefinition
{
    public const PATH = 'path';
    public const QUERY = 'query';

    /**
     * @param 'string'|'integer'|'number'|'boolean'|'array' $type
     * @param self::PATH|self::QUERY $location
     * @param string|null $regex 値全体に照合する正規表現。null は型以外の制約なし
     * @param list<string|int>|null $enum
     * @param array<string, mixed>|null $items 配列の要素のスキーマ
     * @param class-string|null $bindType 束縛時の変換先（backed enum のクラス名など）
     * @param array<string, mixed> $coreArg register_rest_route() の args に渡す形。URL_PARAMS で書かれた定義はそのまま保つ
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $location,
        public readonly bool $required,
        public readonly ?string $regex,
        public readonly string $description,
        public readonly bool $hasDefault = false,
        public readonly mixed $default = null,
        public readonly ?array $enum = null,
        public readonly int|float|null $minimum = null,
        public readonly int|float|null $maximum = null,
        public readonly ?array $items = null,
        public readonly ?string $bindType = null,
        private readonly array $coreArg = [],
    ) {}

    public function isPath(): bool
    {
        return $this->location === self::PATH;
    }

    /**
     * 検証と束縛に効く宣言（型、位置、必須、正規表現、enum、最小・最大、要素型、束縛先、デフォルト値）が同じか。
     * description は検証に関係しないので比べない
     */
    public function sameConstraintsAs(self $other): bool
    {
        return $this->type === $other->type
            && $this->location === $other->location
            && $this->required === $other->required
            && $this->regex === $other->regex
            && $this->hasDefault === $other->hasDefault
            && $this->default === $other->default
            && $this->enum === $other->enum
            && $this->minimum === $other->minimum
            && $this->maximum === $other->maximum
            && $this->items === $other->items
            && $this->bindType === $other->bindType;
    }

    /** ルート正規表現に埋め込む名前付きキャプチャ */
    public function pathCapture(): string
    {
        return "(?P<{$this->name}>" . ($this->regex ?? ParameterRegex::PATH_SEGMENT) . ')';
    }

    /**
     * register_rest_route() の args 1 件分
     *
     * @return array<string, mixed>
     */
    public function toCoreArg(): array
    {
        return $this->coreArg;
    }

    /**
     * OpenAPI の parameters 1 件分
     *
     * @return array<string, mixed>
     */
    public function toOpenApiParameter(): array
    {
        $schema = ['type' => $this->type];
        if ($this->type === 'array') {
            // OpenAPI 3.0 では array の schema に items（Schema Object）が必須。要素型が読めなければ任意の {}。
            // PHP の空配列は JSON で [] になるので、空のときは stdClass で {} にする
            $schema['items'] = $this->items ? $this->items : new \stdClass();
        }
        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }
        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }
        // null のデフォルトは「省略すると null」で、OpenAPI の default として意味を持たないので出さない
        if ($this->hasDefault && $this->default !== null) {
            $schema['default'] = $this->default;
        }
        return [
            'name' => $this->name,
            'required' => $this->required,
            'description' => $this->description,
            'in' => $this->location,
            'schema' => $schema,
        ];
    }
}
