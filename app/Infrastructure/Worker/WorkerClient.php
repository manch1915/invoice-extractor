<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;

/**
 * Talks to the Python service that reads pages (text layer, OCR) and proposes field values.
 */
class WorkerClient
{
    public function __construct(
        private readonly Http $http,
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout,
        private readonly string $engine,
    ) {}

    /**
     * @param  resource|string  $contents
     * @param  list<array{code: string, name: string}>  $categories
     *
     * @throws WorkerException
     */
    public function extract($contents, string $filename, array $categories): ExtractionResult
    {
        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->withToken($this->token)
                ->timeout($this->timeout)
                ->retry(2, 2000, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->attach('file', $contents, $filename)
                ->post('/v1/extract', [
                    'categories' => json_encode($categories, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'engine' => $this->engine,
                ]);
        } catch (ConnectionException $e) {
            throw WorkerException::unreachable($e);
        }

        if ($response->status() === 422) {
            throw WorkerException::unreadable((string) $response->json('detail', 'unreadable document'));
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw WorkerException::failed($e);
        }

        return ExtractionResult::fromArray((array) $response->json());
    }
}
