<?php
namespace Wp\Resta;

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
    /** @var array<class-string> */
    public readonly array $adapters;
    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

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
        foreach (['listeners', 'adapters'] as $key) {
            $prop = $config[$key] ?? [];
            assert(is_array($prop));
            $prop = array_filter($prop, 'is_string');
            $prop = array_unique($prop, SORT_STRING);
            $this->$key = array_values($prop);
        }
    }

    public function get(string $key) : mixed
    {
        return $this->config[$key] ?? null;
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }
}
