<?php
/**
 * @var string $schemaUrl スキーマを返す URL。SwaggerHook が渡す
 * @var string $nonce REST API の cookie 認証に付ける nonce。SwaggerHook が渡す
 */
?>
<script>
window.onload = function() {
    const schemaUrl = <?php echo wp_json_encode($schemaUrl); ?>;
    const nonce = <?php echo wp_json_encode($nonce); ?>;
    // Begin Swagger UI call region
    const ui = SwaggerUIBundle({
        url: schemaUrl,
        // nonce を付けるのはスキーマの取得だけ。「Try it out」のリクエストには付けない
        requestInterceptor: function (request) {
            if (request.loadSpec || request.url === schemaUrl) {
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
