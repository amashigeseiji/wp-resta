<?php
/**
 * Bootstrap for unit tests
 *
 * Unit tests use Brain Monkey for mocking WordPress functions.
 * No WordPress installation required.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// WordPress クラスの最小実装（テスト専用）
// グローバル名前空間のため PSR-4 スキャン対象外のここで定義する。
if (!class_exists('WP_HTTP_Response')) {
    class WP_HTTP_Response
    {
        protected mixed $data;
        /** @var array<string, string> */
        protected array $headers;
        protected int $status;

        /**
         * @param array<string, string> $headers
         */
        public function __construct(mixed $data = null, int $status = 200, array $headers = [])
        {
            $this->data    = $data;
            $this->status  = $status;
            $this->headers = $headers;
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        /** @return array<string, string> */
        public function get_headers(): array
        {
            return $this->headers;
        }

        public function header(string $key, string $value, bool $replace = true): void
        {
            if ($replace || !isset($this->headers[$key])) {
                $this->headers[$key] = $value;
            }
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response extends WP_HTTP_Response {}
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request implements ArrayAccess
    {
        /** @var array<string, mixed> */
        private array $params = [];

        public function __construct(
            private string $method = '',
            private string $route = ''
        ) {
        }

        public function getRoute(): string
        {
            return $this->route;
        }

        public function set_param(string $param, mixed $value): void
        {
            $this->params[$param] = $value;
        }

        /** @return array<string, mixed> */
        public function get_query_params(): array
        {
            return $this->params;
        }

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->params[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->params[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->params[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->params[$offset]);
        }
    }
}
