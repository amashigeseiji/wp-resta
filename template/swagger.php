<!DOCTYPE html>
<html lang="<?php language_attributes();?>">
  <head>
    <meta charset="UTF-8">
    <title>REST API doc</title>
    <?php wp_head();?>
    <script>
    window.onload = function() {
        // Begin Swagger UI call region
        const ui = SwaggerUIBundle({
            url: swaggerSetting.schemaUrl,
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
      html
      {
        box-sizing: border-box;
        overflow: -moz-scrollbars-vertical;
        overflow-y: scroll;
      }
      *,
      *:before,
      *:after
      {
        box-sizing: inherit;
      }
      body
      {
        margin:0;
        background: #fafafa;
      }
    </style>
  </head>
  <body>
    <div id="swagger-ui"></div>
    <?php wp_footer();?>
  </body>
</html>
