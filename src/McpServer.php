<?php

namespace Larwell\QuickMcp;

use Throwable;

class McpServer
{
    public const PROTOCOL_VERSION = '2025-06-18';

    /** @var array<string, array{definition: array, handler: callable}> */
    protected array $tools = [];

    public function __construct(
        protected string $name = 'Laravel',
        protected string $version = '1.0.0',
    ) {
    }

    /**
     * Register a tool.
     *
     * $schema is either a full JSON Schema object (['type' => 'object', ...])
     * or a shorthand map of required arguments: ['city' => 'string', 'days' => 'integer'].
     */
    public function tool(string $name, string $description, array $schema, callable $handler): static
    {
        if (($schema['type'] ?? null) !== 'object') {
            $schema = [
                'type' => 'object',
                'properties' => array_map(fn ($type) => ['type' => $type], $schema) ?: new \stdClass(),
                'required' => array_keys($schema),
            ];
        }

        $this->tools[$name] = [
            'definition' => ['name' => $name, 'description' => $description, 'inputSchema' => $schema],
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Handle a decoded JSON-RPC payload (single or batch).
     * Returns the response payload, or null when nothing should be sent back (notifications only).
     */
    public function handle(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return $this->error(null, -32700, 'Parse error');
        }

        if ($payload === []) {
            return $this->error(null, -32600, 'Invalid Request');
        }

        if (array_is_list($payload)) {
            $responses = array_values(array_filter(array_map(fn ($message) => $this->handleMessage($message), $payload)));

            return $responses ?: null;
        }

        return $this->handleMessage($payload);
    }

    protected function handleMessage(mixed $message): ?array
    {
        if (! is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($message['method'] ?? null)) {
            return $this->error(is_array($message) ? ($message['id'] ?? null) : null, -32600, 'Invalid Request');
        }

        $isNotification = ! array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        $response = match ($message['method']) {
            'initialize' => $this->result($id, [
                // shortcut: echoes the client's version instead of negotiating; add a supported list if a version breaks
                'protocolVersion' => is_string($params['protocolVersion'] ?? null) ? $params['protocolVersion'] : self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => $this->name, 'version' => $this->version],
            ]),
            'ping' => $this->result($id, new \stdClass()),
            'tools/list' => $this->result($id, ['tools' => array_column($this->tools, 'definition')]),
            'tools/call' => $this->callTool($id, $params),
            default => $this->error($id, -32601, "Method not found: {$message['method']}"),
        };

        return $isNotification ? null : $response;
    }

    protected function callTool(mixed $id, array $params): array
    {
        $tool = $this->tools[$params['name'] ?? ''] ?? null;

        if ($tool === null) {
            return $this->error($id, -32602, 'Unknown tool: '.($params['name'] ?? ''));
        }

        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        foreach ($tool['definition']['inputSchema']['required'] ?? [] as $required) {
            if (! array_key_exists($required, $arguments)) {
                return $this->error($id, -32602, "Missing required argument: {$required}");
            }
        }

        try {
            $output = ($tool['handler'])($arguments);
        } catch (Throwable $e) {
            // MCP reports tool failures as results so the model can see and react to them.
            return $this->result($id, ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true]);
        }

        $text = is_string($output) ? $output : json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->result($id, ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false]);
    }

    protected function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    protected function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
