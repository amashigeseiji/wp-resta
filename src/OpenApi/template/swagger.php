<script>
window.onload = function() {
    // Begin Swagger UI call region
    const ui = SwaggerUIBundle({
        url: "/rest-api/schema",
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
