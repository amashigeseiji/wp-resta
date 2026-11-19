<?php
/**
 * @var string $schemaUrl スキーマを返す URL。SwaggerHook が渡す
 * @var string|null $nonce REST API の cookie 認証に付ける nonce。付けないときは null。SwaggerHook が渡す
 */
?>
<script>
window.onload = function() {
    const schemaUrl = <?php echo wp_json_encode($schemaUrl); ?>;
    const nonce = <?php echo wp_json_encode($nonce); ?>;
    const sameUrl = function (a, b) {
        try {
            return new URL(a, window.location.href).href === new URL(b, window.location.href).href;
        } catch (e) {
            return false;
        }
    };
    // Begin Swagger UI call region
    const ui = SwaggerUIBundle({
        url: schemaUrl,
        // nonce を付けるのは、SwaggerHook が渡した URL へのリクエストだけ。
        // 「Try it out」や、Explore で読み込んだ別の URL には付けない
        requestInterceptor: function (request) {
            if (nonce !== null && sameUrl(request.url, schemaUrl)) {
                request.headers['X-WP-Nonce'] = nonce;
            }
            return request;
        },
        dom_id: '#swagger-ui',
        validatorUrl : null,
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        plugins: [
            SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout"
    })
    window.ui = ui
}
</script>
<style>
  html {
    box-sizing: border-box;
    overflow: -moz-scrollbars-vertical;
    overflow-y: scroll;
  }
  *, *:before, *:after {
    box-sizing: inherit;
  }
  body
  {
    margin:0;
    background: #fafafa;
  }
  #wpcontent {
    padding-left: 0;
  }
  .swagger-ui .arrow {
    visibility: hidden;
  }
</style>
<div id="swagger-ui"></div>
