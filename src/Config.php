<?php
namespace Wp\Resta;

use Wp\Resta\Kernel\WpKernelAdapter;

class Config
{
    /** @var array<string[]> */
    public readonly array $routeDirectory;
    /** @var array<string[]> */
    public readonly array $schemaDirectory;
    /** @var array<int|class-string, object|class-string> */
    public readonly array $dependencies;
    /** @var array<class-string<\Wp\Resta\Hooks\HookProviderInterface>> */
    public readonly array $hooks;
    /** @var array<class-string> */
    public readonly array $listeners;
    /** @var array<class-string> WordPress ライフサイクルの橋渡し。省略時は WpKernelAdapter */
    public readonly array $adapters;
    /**
     * OpenAPI の components.securitySchemes にそのまま出す認証方式。名前 => Security Scheme Object。
     * 省略時は WordPress 標準の cookie + nonce。中身は検証しない
     *
     * @var array<string, array<string, mixed>>
     */
    public readonly array $securitySchemes;
    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

    /** securitySchemes を省略したときに出す方式 */
    private const DEFAULT_SECURITY_SCHEMES = [
        'cookieNonce' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-WP-Nonce'],
    ];

    /**
     * @template T
     * @param array{
     *    autoloader?: string,
     *    routeDirectory?: array<string[]>,
     *    schemaDirectory?: array<string[]>,
     *    dependencies?: array<class-string<T>, T|class-string<T>>,
     *    hooks?: array<class-string<\Wp\Resta\Hooks\HookProviderInterface>>,
     *    listeners?: array<class-string>,
     *    adapters?: array<class-string>,
     *    securitySchemes?: array<string, array<string, mixed>>,
     * } $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // オプショナル項目
        $this->routeDirectory = $config['routeDirectory'] ?? [];
        $this->schemaDirectory = $config['schemaDirectory'] ?? [];
        $this->dependencies = $config['dependencies'] ?? [];

        // hooks のバリデーション: 文字列のみフィルタ、重複排除
        $hooks = $config['hooks'] ?? [];
        assert(is_array($hooks));
        $hooks = array_filter($hooks, 'is_string');
        $hooks = array_unique($hooks, SORT_STRING);
        $this->hooks = array_values($hooks);

        // listeners/adapters のバリデーション: 文字列のみフィルタ、重複排除
        $this->listeners = $this->normalizeStringArray($config['listeners'] ?? []);
        // adapters はキーを省略したときだけ WpKernelAdapter を既定にする。明示した空配列はそのまま
        $this->adapters = array_key_exists('adapters', $config)
            ? $this->normalizeStringArray($config['adapters'])
            : [WpKernelAdapter::class];
        // 書けば置き換える。明示した空配列は「security を出さない」
        $this->securitySchemes = $config['securitySchemes'] ?? self::DEFAULT_SECURITY_SCHEMES;
    }

    public function get(string $key) : mixed
    {
        return $this->config[$key] ?? null;
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * @param string[] $array
     * @return string[]
     */
    private function normalizeStringArray(array $array): array
    {
        return array_values(array_unique(array_filter($array, 'is_string'), SORT_STRING));
    }
}
