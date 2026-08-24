<?php

declare(strict_types=1);

namespace App\Test\AuthBff;

use AuthBff;
use AuthBffTransport;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../public/auth-bff/AuthBff.php';

final class FakeTransport implements AuthBffTransport
{
    public int $calls = 0;

    public ?string $url = null;

    public string $method = '';

    /** @var list<string>|null */
    public ?array $headers = null;

    public string $body = '';

    public function __construct(
        private int $status = 200,
        private string $contentType = 'application/json',
        private string $responseBody = '{"files":[]}',
    ) {
    }

    public function send(string $url, string $method, array $headers, string $body): array
    {
        $this->calls++;
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
        $this->body = $body;

        return [
            'status' => $this->status,
            'contentType' => $this->contentType,
            'body' => $this->responseBody,
        ];
    }
}

final class AuthBffTest extends TestCase
{
    private bool $hadBaseUrl = false;

    private mixed $previousBaseUrl = null;

    protected function setUp(): void
    {
        $this->hadBaseUrl = array_key_exists('BASEURL', $GLOBALS);
        $this->previousBaseUrl = $GLOBALS['BASEURL'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadBaseUrl) {
            $GLOBALS['BASEURL'] = $this->previousBaseUrl;
        } else {
            unset($GLOBALS['BASEURL']);
        }
    }

    public function testUnauthorizedEmptySessionDoesNotCallApi(): void
    {
        $transport = new FakeTransport();
        $result = (new AuthBff($transport))->handle($this->server('/auth-bff/files'), [], '');

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testUnauthorizedMissingUserTokenDoesNotCallApi(): void
    {
        $transport = new FakeTransport();
        $result = (new AuthBff($transport))->handle(
            $this->server('/auth-bff/files'),
            ['User' => ['id' => 'someone']],
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
    }

    public function testAuthorizedForwardsToPinnedApi(): void
    {
        $transport = new FakeTransport(200, 'application/json', '{"files":[]}');
        $result = (new AuthBff($transport))->handle(
            $this->server('/auth-bff/files?offset=0&limit=50', 'offset=0&limit=50'),
            $this->session('session-jwt'),
            '',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame('http://127.0.0.1/api/v1/files?offset=0&limit=50', $transport->url);
        $this->assertSame('GET', $transport->method);
        $this->assertSame('Authorization: Bearer session-jwt', $this->header($transport->headers, 'Authorization'));
        $this->assertNull($this->header($transport->headers, 'Cookie'));
        $this->assertSame(200, $result['status']);
        $this->assertSame('{"files":[]}', $result['body']);
    }

    #[DataProvider('methodAndBodyProvider')]
    public function testAuthorizedForwardsMethodAndBody(
        string $method,
        string $inboundBody,
        string $expectedOutboundBody,
    ): void {
        $transport = new FakeTransport();
        $server = $this->server('/auth-bff/files', '', $method);
        $server['CONTENT_TYPE'] = 'application/json';

        (new AuthBff($transport))->handle(
            $server,
            $this->session('session-jwt'),
            $inboundBody,
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame($method, $transport->method);
        $this->assertSame($expectedOutboundBody, $transport->body);
        $this->assertSame('Content-Type: application/json', $this->header($transport->headers, 'Content-Type'));
    }

    public function testAuthorizedIgnoresClientAuthorizationHeader(): void
    {
        $transport = new FakeTransport();
        $server = $this->server('/auth-bff/files');
        $server['HTTP_AUTHORIZATION'] = 'Bearer evil';

        (new AuthBff($transport))->handle($server, $this->session('session-jwt'), '');

        $this->assertSame(1, $transport->calls);
        $this->assertSame('Authorization: Bearer session-jwt', $this->header($transport->headers, 'Authorization'));
    }

    #[DataProvider('baseUrlPrefixProvider')]
    public function testAuthorizedStripsBaseUrlPrefixFromRequestUri(string $baseUrl, string $requestUri): void
    {
        $GLOBALS['BASEURL'] = $baseUrl;

        $transport = new FakeTransport();
        $result = (new AuthBff($transport))->handle(
            $this->server($requestUri, 'offset=0&limit=50'),
            $this->session('session-jwt'),
            '',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame('http://127.0.0.1/api/v1/files?offset=0&limit=50', $transport->url);
        $this->assertSame(200, $result['status']);
    }

    public function testAuthorizedUnknownPrefixIsForbidden(): void
    {
        $transport = new FakeTransport();
        $result = (new AuthBff($transport))->handle(
            $this->server('/auth-bff/tools'),
            $this->session('session-jwt'),
            '',
        );

        $this->assertSame(403, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('FORBIDDEN', json_decode($result['body'], true)['code']);
    }

    #[DataProvider('injectionPathProvider')]
    public function testAuthorizedInjectionPathsDoNotCallApi(string $requestUri): void
    {
        $transport = new FakeTransport();
        $result = (new AuthBff($transport))->handle(
            $this->server($requestUri),
            $this->session('session-jwt'),
            '',
        );

        $this->assertSame(403, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('FORBIDDEN', json_decode($result['body'], true)['code']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function injectionPathProvider(): array
    {
        return [
            // traversal: leave /auth-bff and hit another local path
            'parent traversal' => ['/auth-bff/../api/v1/docs'],
            // encoded traversal: %2e%2e must still be rejected after decode
            'encoded parent traversal' => ['/auth-bff/%2e%2e/api/v1/docs'],
            // scheme/host: colon and // must not become an absolute URL
            'scheme injection' => ['/auth-bff/https://evil.example/secret'],
            // double slash: files//… is not a safe relative path
            'double slash' => ['/auth-bff/files//evil'],
            // allowlist is a path segment, not a string prefix (filesevil ≠ files)
            'allowlist string prefix' => ['/auth-bff/filesevil'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function baseUrlPrefixProvider(): array
    {
        return [
            'app prefix' => ['/vre', '/vre/auth-bff/files?offset=0&limit=50'],
            'trailing slash on BASEURL' => ['/vre/', '/vre/auth-bff/files?offset=0&limit=50'],
            'nested prefix' => ['/openvre', '/openvre/auth-bff/files?offset=0&limit=50'],
            'root BASEURL is a no-op' => ['/', '/auth-bff/files?offset=0&limit=50'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function methodAndBodyProvider(): array
    {
        $payload = '{"name":"readme.txt"}';

        return [
            'POST forwards body' => ['POST', $payload, $payload],
            'PATCH forwards body' => ['PATCH', $payload, $payload],
            'GET strips body' => ['GET', $payload, ''],
            'HEAD strips body' => ['HEAD', $payload, ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function server(string $requestUri, string $queryString = '', string $method = 'GET'): array
    {
        return [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $requestUri,
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function session(string $accessToken): array
    {
        return [
            'userToken' => new AccessToken(['access_token' => $accessToken]),
        ];
    }

    /**
     * @param list<string>|null $headers
     */
    private function header(?array $headers, string $name): ?string
    {
        if ($headers === null) {
            return null;
        }

        foreach ($headers as $header) {
            if (stripos($header, $name . ':') === 0) {
                return $header;
            }
        }

        return null;
    }
}
