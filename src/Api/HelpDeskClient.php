<?php

namespace JeffersonGoncalves\HelpDesk\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;

/**
 * The satellite's side of the wire.
 *
 * Signs every request and turns every failure into something that names what
 * went wrong, rather than handing back a Response for each caller to interpret
 * differently.
 */
class HelpDeskClient
{
    public function __construct(protected HelpDeskSigner $signer = new HelpDeskSigner) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query, null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        return $this->send('POST', $path, [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    protected function send(string $method, string $path, array $query, ?array $payload): array
    {
        $url = $this->url($path, $query);
        $body = $payload === null ? '' : (string) json_encode($payload);

        $headers = $this->signer->headersFor($method, $url, $body, $this->appKey(), $this->secret());

        try {
            $response = Http::withHeaders($headers + ['Accept' => 'application/json'])
                ->withBody($body, 'application/json')
                ->timeout($this->timeout())
                // Deliberately no retry. A write that timed out may have been
                // committed, and retrying with a fresh nonce would create a
                // duplicate rather than being rejected as a replay.
                ->send($method, $url);
        } catch (ConnectionException $e) {
            throw HelpDeskApiException::unreachable($e->getMessage());
        }

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        if ($response->successful()) {
            $decoded = $response->json();

            return is_array($decoded) ? $decoded : [];
        }

        // 404 is the only one that maps to a documented package exception, so
        // that findByUuid() behaves the same on both drivers.
        throw match ($response->status()) {
            401 => HelpDeskApiException::unauthorized(),
            404 => TicketNotFoundException::withUuid('(not found)'),
            // The move was refused by the transition table, which is what the
            // contract promises for a status change on either driver.
            409 => new InvalidStatusTransitionException((string) ($response->json('message') ?? 'This status change is not allowed.')),
            422 => HelpDeskApiException::invalid((array) ($response->json('errors') ?? [])),
            default => HelpDeskApiException::failed($response->status(), $response->body()),
        };
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function url(string $path, array $query = []): string
    {
        $base = rtrim($this->config('api.url'), '/');
        $prefix = trim((string) config('help-desk.api.prefix', 'help-desk/api'), '/');
        $url = $base.'/'.$prefix.'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    protected function appKey(): string
    {
        return $this->config('app.key');
    }

    protected function secret(): string
    {
        return $this->config('api.secret');
    }

    protected function timeout(): int
    {
        $timeout = config('help-desk.api.timeout', 10);

        return is_numeric($timeout) ? (int) $timeout : 10;
    }

    /**
     * Fails naming the key rather than sending an unsigned request to an empty
     * host and reporting whatever that produces.
     */
    protected function config(string $key): string
    {
        $value = config("help-desk.{$key}");

        if (! is_string($value) || $value === '') {
            throw HelpDeskApiException::notConfigured($key);
        }

        return $value;
    }
}
