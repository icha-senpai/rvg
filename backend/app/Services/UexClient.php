<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UexClient
{
    public function fetch(string $resource, array $query = []): mixed
    {
        $response = null;
        $resourceWithQuery = $query === [] ? $resource : $resource . '?' . http_build_query($query);

        foreach ([1, 2, 4] as $attempt => $backoffSeconds) {
            $response = $this->request()->get($resource, $query);

            if ($response->status() === 429 && $attempt < 2) {
                $retryAfter = (int) ($response->header('Retry-After') ?: $backoffSeconds);
                sleep(max(1, $retryAfter));
                continue;
            }

            break;
        }

        if (! $response) {
            throw new RuntimeException("UEX request for [{$resourceWithQuery}] did not return a response.");
        }

        if ($response->failed()) {
            try {
                $response->throw();
            } catch (RequestException $e) {
                throw new RuntimeException("UEX request failed for [{$resourceWithQuery}]: {$e->getMessage()}", previous: $e);
            }
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException("UEX response for [{$resourceWithQuery}] was not valid JSON.");
        }

        $status = $payload['status'] ?? null;

        if ($status !== null && $status !== 'ok') {
            $message = $payload['message'] ?? 'Unknown UEX API error.';
            throw new RuntimeException("UEX returned [{$status}] for [{$resourceWithQuery}]: {$message}");
        }

        if (! array_key_exists('data', $payload)) {
            throw new RuntimeException("UEX response for [{$resourceWithQuery}] did not include a data payload.");
        }

        return $payload['data'];
    }

    protected function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.uex.base_url', 'https://api.uexcorp.uk/2.0'), '/');
        $timeout = (int) config('services.uex.timeout', 20);
        $token = (string) config('services.uex.token', '');
        $clientVersion = trim((string) config('services.uex.client_version', ''));

        $request = Http::acceptJson()
            ->baseUrl($baseUrl)
            ->timeout($timeout);

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        if ($clientVersion !== '') {
            $request = $request->withHeaders([
                'X-Client-Version' => $clientVersion,
            ]);
        }

        return $request;
    }
}
