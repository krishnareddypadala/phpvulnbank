<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Support\OpenApiSpec;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    /**
     * GET /api/v2/openapi.json
     *
     * Served without authentication so students can load it into Postman,
     * Burp, Insomnia or Swagger UI without setting anything up first.
     *
     * Worth noticing in its own right: this is a complete machine-readable
     * inventory of every endpoint, including the webshell and the ungated
     * admin actions. Real deployments leak exactly this (OWASP API9). Here it
     * is intentional, because the map is the teaching material.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(
            OpenApiSpec::toArray(),
            200,
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
