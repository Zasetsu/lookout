<?php

namespace Zasetsu\Lookout\Trace;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Zasetsu\Lookout\Pipeline\Redactor;

class ExecutionContext
{
    public string $traceId;

    public string $type;

    public string $name;

    public string $status = 'success';

    public float $timestamp;

    public ?int $duration = null;

    public ?int $memoryPeak = null;

    public ?string $userId = null;

    public ?string $ip = null;

    public ?string $method = null;

    public ?string $url = null;

    public ?array $requestHeaders = null;

    public ?array $requestBody = null;

    public ?int $responseStatus = null;

    public ?array $responseHeaders = null;

    public array $tags = [];

    public ?string $environment = null;

    public function __construct()
    {
        $this->traceId = Str::uuid()->toString();
        $this->timestamp = microtime(true);
        $this->environment = app()->environment();
    }

    public static function forRequest(Request $request): self
    {
        $ctx = new self;
        $ctx->type = 'request';
        $ctx->method = $request->method();
        $ctx->ip = $request->ip();
        $route = $request->route();
        $ctx->name = $route instanceof Route ? $route->uri() : self::sanitizePath($request->decodedPath());
        $ctx->url = self::sanitizedUrlForRequest($request);
        $userId = $request->user()?->getAuthIdentifier();
        $ctx->userId = $userId !== null ? (string) $userId : null;
        $ctx->requestHeaders = app(Redactor::class)->redact($request->headers->all());

        return $ctx;
    }

    public static function sanitizedUrlForRequest(Request $request): string
    {
        $route = $request->route();
        $path = $route instanceof Route ? $route->uri() : self::sanitizePath($request->decodedPath());

        if ($path === '/' || $path === '') {
            return $request->getSchemeAndHttpHost();
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    public static function forCommand(Command|string $command): self
    {
        $ctx = new self;
        $ctx->type = 'command';
        $ctx->name = is_string($command) ? $command : ($command->getName() ?? get_class($command));

        return $ctx;
    }

    public static function forScheduledTask(string $description): self
    {
        $ctx = new self;
        $ctx->type = 'scheduled_task';
        $ctx->name = $description;

        return $ctx;
    }

    public function finish(): void
    {
        $this->duration = (int) ((microtime(true) - $this->timestamp) * 1000);
        $this->memoryPeak = memory_get_peak_usage(true);
    }

    public function markFailed(): void
    {
        $this->status = 'error';
    }

    protected static function sanitizePath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return '/';
        }

        $previous = null;

        foreach ($segments as $index => $segment) {
            if (self::isSensitivePathValue($segment, $previous)) {
                $segments[$index] = '***';
            }

            $previous = $segment;
        }

        return implode('/', $segments);
    }

    protected static function isSensitivePathValue(string $segment, ?string $previous): bool
    {
        if ($previous !== null && self::containsSensitiveWord($previous)) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $segment) === 1) {
            return true;
        }

        return preg_match('/^[A-Za-z0-9_.=-]{32,}$/', $segment) === 1;
    }

    protected static function containsSensitiveWord(string $segment): bool
    {
        return preg_match('/(password|reset|token|secret|invite|key|signature|auth|verify|verification)/i', $segment) === 1;
    }

    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'timestamp' => date('Y-m-d H:i:s', (int) $this->timestamp),
            'duration' => $this->duration,
            'memory_peak' => $this->memoryPeak,
            'user_id' => $this->userId ? (string) $this->userId : null,
            'ip' => $this->ip,
            'method' => $this->method,
            'url' => $this->url,
            'request_headers' => $this->requestHeaders ? json_encode($this->requestHeaders) : null,
            'request_body' => $this->requestBody ? json_encode($this->requestBody) : null,
            'response_status' => $this->responseStatus,
            'response_headers' => $this->responseHeaders ? json_encode($this->responseHeaders) : null,
            'tags' => ! empty($this->tags) ? json_encode($this->tags) : null,
            'environment' => $this->environment,
        ];
    }
}
