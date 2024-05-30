<?php
namespace Wp\Resta;

class Config
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * @template T
     * @param array{
     *    autoloader?: string,
     *    routeDirectory: array<string[]>,
     *    schemaDirectory?: array<string[]>,
     *    dependencies?: array<class-string<T>, T|class-string<T>>,
     *    use-swagger?: bool
     * } $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
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
