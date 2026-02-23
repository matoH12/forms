<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Forms API",
    version: "1.0.0",
    description: "REST API for accessing form data and approved submissions"
)]
#[OA\Server(url: "/", description: "API Server")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "Token",
    description: "API token z nastavení profilu"
)]
class OpenApi
{
}
