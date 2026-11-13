<?php

namespace Larwell\QuickMcp;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class LaravelQuickMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/quick-mcp.php', 'quick-mcp');

        $this->app->singleton(McpServer::class, fn ($app) => new McpServer(
            (string) $app['config']->get('quick-mcp.server.name', 'Laravel'),
            (string) $app['config']->get('quick-mcp.server.version', '1.0.0'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/quick-mcp.php' => config_path('quick-mcp.php'),
            ], 'quick-mcp-config');
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        $this->app['router']
            ->post($this->app['config']->get('quick-mcp.path', 'mcp'), function (Request $request, McpServer $server) {
                $response = $server->handle(json_decode($request->getContent(), true));

                return $response === null
                    ? response()->noContent(202)
                    : response()->json($response, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            })
            ->middleware($this->app['config']->get('quick-mcp.middleware', []))
            ->name('quick-mcp');
    }
}
