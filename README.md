# Laravel Quick MCP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/larwell/laravel-quick-mcp.svg?style=flat-square)](https://packagist.org/packages/larwell/laravel-quick-mcp)
[![Total Downloads](https://img.shields.io/packagist/dt/larwell/laravel-quick-mcp.svg?style=flat-square)](https://packagist.org/packages/larwell/laravel-quick-mcp)
[![License](https://img.shields.io/packagist/l/larwell/laravel-quick-mcp.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/larwell/laravel-quick-mcp.svg?style=flat-square)](https://packagist.org/packages/larwell/laravel-quick-mcp)

Effortless **Model Context Protocol (MCP)** server integration for Laravel applications.
Register tools in a few lines and serve them over a JSON-RPC 2.0 endpoint that Claude, Cursor, and other MCP clients can talk to.

- Zero dependencies beyond `illuminate/support`
- Auto-discovered service provider, nothing to wire up
- Handles `initialize`, `ping`, `tools/list`, `tools/call`, notifications and batches

## Installation

```bash
composer require larwell/laravel-quick-mcp
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=quick-mcp-config
```

## Usage

Register tools in the `boot()` method of any service provider (for example `AppServiceProvider`):

```php
use Larwell\QuickMcp\McpServer;

app(McpServer::class)->tool('add', 'Add two numbers', ['a' => 'number', 'b' => 'number'],
    fn (array $args) => $args['a'] + $args['b']
);
```

That's it. Your MCP server is now live at `POST /mcp`.

### Tool schemas

The third argument accepts a shorthand map (every key is a required argument) or a full JSON Schema:

```php
app(McpServer::class)->tool('search_users', 'Find users by name', [
    'type' => 'object',
    'properties' => [
        'query' => ['type' => 'string', 'description' => 'Part of a name'],
        'limit' => ['type' => 'integer'],
    ],
    'required' => ['query'],
], fn (array $args) => User::where('name', 'like', "%{$args['query']}%")
    ->limit($args['limit'] ?? 10)
    ->get(['id', 'name']));
```

Handlers may return a string (sent as is) or anything JSON-serialisable (sent as JSON text).
A thrown exception is returned to the client as a tool error (`isError: true`) instead of crashing the request.

### Try it

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"add","arguments":{"a":2,"b":3}}}'
```

## Configuration

`config/quick-mcp.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `path` | `mcp` | URI of the endpoint (`QUICK_MCP_PATH`) |
| `middleware` | `[]` | Middleware for the endpoint |
| `server.name` | `APP_NAME` | Name reported to clients (`QUICK_MCP_NAME`) |
| `server.version` | `1.0.0` | Version reported to clients (`QUICK_MCP_VERSION`) |

> **Security:** the endpoint is public by default. Before you deploy, protect it with middleware, for example `'middleware' => ['auth:sanctum']`.

## Requirements

- PHP 8.2+
- Laravel 10, 11 or 12

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

---

Maintained by **Larwell Technologies** ([https://larwell.com](https://larwell.com))
