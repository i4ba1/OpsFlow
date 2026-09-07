<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'opsflow-api']);
});

// Health probe for orchestrators/load balancers — keep network-firewalled.
Route::get('/internal/health', HealthController::class);

// OpenAPI documentation viewer (PRD §64). Static spec lives in public/.
Route::get('/docs/api', function () {
    return <<<'HTML'
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8" />
        <title>OpsFlow API — OpenAPI</title>
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
      </head>
      <body>
        <div id="swagger-ui"></div>
        <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
        <script>
          window.onload = () => {
            window.ui = SwaggerUIBundle({
              url: '/openapi.yaml',
              dom_id: '#swagger-ui',
              deepLinking: true,
            });
          };
        </script>
      </body>
    </html>
    HTML;
});

Route::get('/openapi.yaml', function () {
    $path = public_path('openapi.yaml');

    abort_unless(is_file($path), 404);

    return response(File::get($path), 200, [
        'Content-Type' => 'application/yaml',
    ]);
});
