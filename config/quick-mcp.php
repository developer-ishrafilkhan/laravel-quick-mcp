<?php

return [

    // URI the MCP JSON-RPC endpoint is served on (POST only).
    'path' => env('QUICK_MCP_PATH', 'mcp'),

    // Middleware applied to the endpoint. Protect it, e.g. ['auth:sanctum'].
    'middleware' => [],

    // Reported to clients in the "initialize" handshake.
    'server' => [
        'name' => env('QUICK_MCP_NAME', env('APP_NAME', 'Laravel')),
        'version' => env('QUICK_MCP_VERSION', '1.0.0'),
    ],

];
