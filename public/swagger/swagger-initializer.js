window.onload = () => {
  window.ui = SwaggerUIBundle({
    url: "/swagger/api.yaml", 
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    layout: "StandaloneLayout"
  });
};
