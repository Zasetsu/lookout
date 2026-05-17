<?php

namespace Zasetsu\Lookout\Pipeline;

class Redactor
{
    protected RedactionPolicy $policy;

    public function __construct(?RedactionPolicy $policy = null)
    {
        $this->policy = $policy ?? RedactionPolicy::fromConfig();
    }

    public function redact(mixed $data): mixed
    {
        if (is_string($data)) {
            return $this->redactString($data);
        }

        if (is_array($data)) {
            return $this->redactArray($data);
        }

        return $data;
    }

    public function redactUrl(string $url): string
    {
        $redactedUrl = $this->redactString($url);
        $parts = parse_url($redactedUrl);

        if ($parts === false) {
            return $redactedUrl;
        }

        if (isset($parts['path'])) {
            $parts['path'] = $this->redactUrlPath($parts['path']);
        }

        if (isset($parts['query'])) {
            $parts['query'] = $this->redactUrlQuery($parts['query']);
        }

        return $this->buildUrl($parts);
    }

    protected function redactString(string $value): string
    {
        foreach ($this->policy->patterns() as $pattern) {
            if ($this->policy->normalizeForComparison($pattern) === 'authorization') {
                $value = $this->redactAuthorizationHeader($value);
                $value = $this->redactAuthSchemes($value);

                continue;
            }

            $patternRegex = $this->policy->patternRegex($pattern);
            $keyRegex = '/(?<![A-Za-z0-9])("?([A-Za-z0-9_.-]*'.$patternRegex.'[A-Za-z0-9_.-]*)"?\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,}\]&;]+)/i';

            $redacted = preg_replace_callback(
                $keyRegex,
                fn (array $matches): string => $matches[1].$this->redactedStringValue($matches[3]),
                $value
            );

            if (is_string($redacted)) {
                $value = $redacted;
            }

            $wordRedacted = preg_replace(
                '/(?<![A-Za-z0-9])('.$patternRegex.')\s+([^\s,}\]&;]+)/i',
                '$1 ***',
                $value
            );

            if (is_string($wordRedacted)) {
                $value = $wordRedacted;
            }
        }

        return $value;
    }

    protected function redactAuthSchemes(string $value): string
    {
        $redacted = preg_replace(
            '/(?<![A-Za-z0-9])(Bearer|Basic|Digest|Token)\s+([^\s,}\]&;]+)/i',
            '$1 ***',
            $value
        );

        return is_string($redacted) ? $redacted : $value;
    }

    protected function redactAuthorizationHeader(string $value): string
    {
        $withRedactedSchemes = preg_replace(
            '/\b(Authorization\s*[:=]\s*)(Bearer|Basic|Digest|Token)\s+([^\s,}\]&;]+)/i',
            '$1$2 ***',
            $value
        );

        if (is_string($withRedactedSchemes)) {
            $value = $withRedactedSchemes;
        }

        $redacted = preg_replace(
            '/\b(Authorization\s*[:=]\s*)(?!(?:Bearer|Basic|Digest|Token)\s+\*\*\*)("[^"]*"|\'[^\']*\'|[^\s,}\]&;]+)/i',
            '$1***',
            $value
        );

        return is_string($redacted) ? $redacted : $value;
    }

    protected function redactedStringValue(string $value): string
    {
        if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
            return '"***"';
        }

        return '***';
    }

    protected function redactArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->policy->isSensitiveKey($key)) {
                $result[$key] = '***';
            } elseif (is_array($value)) {
                $result[$key] = $this->redactArray($value);
            } elseif (is_string($value)) {
                $result[$key] = $this->redactString($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function containsSensitiveContent(string $text): bool
    {
        return $this->policy->containsSensitiveContent($text);
    }

    protected function redactUrlPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return $path;
        }

        $previous = null;

        foreach ($segments as $index => $segment) {
            if ($this->isSensitivePathValue($segment, $previous)) {
                $segments[$index] = '***';
            }

            $previous = $segment;
        }

        $redacted = implode('/', $segments);

        if (str_starts_with($path, '/')) {
            $redacted = '/'.$redacted;
        }

        if (str_ends_with($path, '/') && $redacted !== '/') {
            $redacted .= '/';
        }

        return $redacted;
    }

    protected function isSensitivePathValue(string $segment, ?string $previous): bool
    {
        $decodedSegment = rawurldecode($segment);

        if ($this->containsSensitiveContent($decodedSegment) && ! $this->isStructuralPathWord($decodedSegment)) {
            return true;
        }

        if ($previous !== null && $this->isSensitivePathMarker($previous) && ! $this->isStructuralPathWord($decodedSegment)) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $decodedSegment) === 1) {
            return true;
        }

        return preg_match('/^[A-Za-z0-9_.=-]{32,}$/', $decodedSegment) === 1;
    }

    protected function redactUrlQuery(string $query): string
    {
        if ($query === '') {
            return $query;
        }

        $segments = explode('&', $query);

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }

            $parts = explode('=', $segment, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$key, $value] = $parts;

            if ($this->policy->isSensitiveKey(rawurldecode($key))) {
                $segments[$index] = $key.'='.$this->redactedQueryValue($value);

                continue;
            }

            $redactedValue = $this->redactNestedQueryValue($value);
            if ($redactedValue !== $value) {
                $segments[$index] = $key.'='.$redactedValue;
            }
        }

        return implode('&', $segments);
    }

    protected function redactedQueryValue(string $value): string
    {
        if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
            return '"***"';
        }

        return '***';
    }

    protected function redactNestedQueryValue(string $value): string
    {
        $decoded = rawurldecode($value);
        if ($decoded === $value) {
            return $value;
        }

        $trimmed = trim($decoded);
        $redacted = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
            ? $this->redactJson($decoded)
            : $this->redactString($decoded);

        if ($redacted === $decoded) {
            return $value;
        }

        return rawurlencode($redacted);
    }

    protected function isSensitivePathMarker(string $segment): bool
    {
        $decodedSegment = rawurldecode($segment);

        return $this->containsSensitiveContent($decodedSegment)
            || preg_match('/^(reset|invite|verify|verification|signature|auth)$/i', $decodedSegment) === 1;
    }

    protected function isStructuralPathWord(string $segment): bool
    {
        return preg_match('/^(password|reset|token|secret|invite|auth|verify|verification|signature|key)$/i', $segment) === 1;
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}  $parts
     */
    protected function buildUrl(array $parts): string
    {
        $url = isset($parts['scheme']) ? $parts['scheme'].':' : '';
        $authority = '';

        if (isset($parts['host'])) {
            if (isset($parts['user'])) {
                $authority .= $parts['user'];
                $authority .= isset($parts['pass']) ? ':***' : '';
                $authority .= '@';
            }

            $authority .= $parts['host'];
            $authority .= isset($parts['port']) ? ':'.$parts['port'] : '';
        }

        if ($authority !== '') {
            $url .= '//'.$authority;
        }

        $url .= $parts['path'] ?? '';
        $url .= isset($parts['query']) ? '?'.$parts['query'] : '';
        $url .= isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $url;
    }

    public function redactJson(string $json): string
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->redactString($json);
        }

        if (! is_array($decoded)) {
            return json_encode($this->redact($decoded));
        }

        return json_encode($this->redactArray($decoded));
    }
}
