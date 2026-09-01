<?php

declare(strict_types=1);

namespace App\Test\AuthBff;

use AuthBff;
use AuthBffTransport;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SessionTokenRefresher;
use SessionTokenRefresherInterface;

require_once __DIR__ . '/../../public/auth-bff/SessionTokenRefresher.php';
require_once __DIR__ . '/../../public/auth-bff/AuthBff.php';

final class FakeTransport implements AuthBffTransport
{
    public int $calls = 0;

    public ?string $url = null;

    public string $method = '';

    /** @var list<string>|null */
    public ?array $headers = null;

    public string $body = '';

    /** @var list<array{status: int, contentType: string, body: string}> */
    private array $responses;

    private int $responseIndex = 0;

    /**
     * @param list<array{status: int, contentType?: string, body?: string}>|null $responses
     */
    public function __construct(
        private int $status = 200,
        private string $contentType = 'application/json',
        private string $responseBody = '{"files":[]}',
        ?array $responses = null,
    ) {
        if ($responses !== null) {
            $this->responses = array_map(
                static fn (array $response): array => [
                    'status' => $response['status'],
                    'contentType' => $response['contentType'] ?? 'application/json',
                    'body' => $response['body'] ?? '',
                ],
                $responses,
            );
        } else {
            $this->responses = [];
        }
    }

    public function send(string $url, string $method, array $headers, string $body): array
    {
        $this->calls++;
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
        $this->body = $body;

        if ($this->responses !== []) {
            $response = $this->responses[min($this->responseIndex, count($this->responses) - 1)];
            $this->responseIndex++;

            return $response;
        }

        return [
            'status' => $this->status,
            'contentType' => $this->contentType,
            'body' => $this->responseBody,
        ];
    }
}

final class FakeSessionTokenRefresher implements SessionTokenRefresherInterface
{
    public int $calls = 0;

    /** @var list<bool> */
    public array $results = [];

    private int $resultIndex = 0;

    /**
     * @param list<bool>|bool $results
     */
    public function __construct(array|bool $results = true)
    {
        $this->results = is_bool($results) ? [$results] : $results;
    }

    public function ensureFreshToken(array &$session, array $server, bool $force = false): bool
    {
        $this->calls++;
        $result = $this->results[min($this->resultIndex, count($this->results) - 1)];
        $this->resultIndex++;

        if ($result) {
            $session['userToken'] = new AccessToken([
                'access_token' => 'refreshed-jwt',
                'expires' => time() + 3600,
            ]);
        }

        return $result;
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

    /**
     * @param array<string, mixed> $session
     * @return array{status: int, contentType: string, body: string}
     */
    private function invokeAuthBff(AuthBff $authBff, array $server, array $session, string $body = ''): array
    {
        return $authBff->handle($server, $session, $body);
    }

    public function testUnauthorizedEmptySessionDoesNotCallApi(): void
    {
        $transport = new FakeTransport();
        $session = [];
        $result = $this->invokeAuthBff(new AuthBff($transport), $this->server('/auth-bff/files'), $session, '');

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testUnauthorizedMissingUserTokenDoesNotCallApi(): void
    {
        $transport = new FakeTransport();
        $session = ['User' => ['id' => 'someone']];
        $result = $this->invokeAuthBff(new AuthBff($transport), $this->server('/auth-bff/files'), $session, '');

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
    }

    public function testAuthorizedForwardsToPinnedApi(): void
    {
        $transport = new FakeTransport(200, 'application/json', '{"files":[]}');
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport),
            $this->server('/auth-bff/files?offset=0&limit=50', 'offset=0&limit=50'),
            $session,
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

        $session = $this->session('session-jwt');

        $this->invokeAuthBff(new AuthBff($transport), $server, $session, $inboundBody);

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

        $session = $this->session('session-jwt');

        $this->invokeAuthBff(new AuthBff($transport), $server, $session, '');

        $this->assertSame(1, $transport->calls);
        $this->assertSame('Authorization: Bearer session-jwt', $this->header($transport->headers, 'Authorization'));
    }

    #[DataProvider('baseUrlPrefixProvider')]
    public function testAuthorizedStripsBaseUrlPrefixFromRequestUri(string $baseUrl, string $requestUri): void
    {
        $GLOBALS['BASEURL'] = $baseUrl;

        $transport = new FakeTransport();
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport),
            $this->server($requestUri, 'offset=0&limit=50'),
            $session,
            '',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame('http://127.0.0.1/api/v1/files?offset=0&limit=50', $transport->url);
        $this->assertSame(200, $result['status']);
    }

    public function testAuthorizedUnknownPrefixIsForbidden(): void
    {
        $transport = new FakeTransport();
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(new AuthBff($transport), $this->server('/auth-bff/jobs'), $session, '');

        $this->assertSame(403, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('FORBIDDEN', json_decode($result['body'], true)['code']);
    }

    public function testAuthorizedToolsServesFixtureWithoutCallingApi(): void
    {
        $transport = new FakeTransport(200, 'application/json', '{"tools":[]}');
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, $this->fixturesPath()),
            $this->server('/auth-bff/tools'),
            $session,
            '',
        );

        $this->assertSame(0, $transport->calls);
        $this->assertSame(200, $result['status']);
        $this->assertSame('application/json', $result['contentType']);

        $body = json_decode($result['body'], true);
        $this->assertSame(
            [
                [
                    'id' => 'seq',
                    'name' => 'Seq',
                    'dataTypes' => ['seq'],
                ],
                [
                    'id' => 'text',
                    'name' => 'Text',
                    'dataTypes' => ['text'],
                ],
            ],
            $body['tools'],
        );
    }

    public function testFilesFixtureServesListWithoutCallingApi(): void
    {
        $transport = new FakeTransport();
        $session = $this->session('session-jwt', 'demo-user@example.com');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(0, $transport->calls);
        $this->assertSame(200, $result['status']);
        $this->assertSame('application/json', $result['contentType']);

        $body = json_decode($result['body'], true);
        $this->assertSame('demo-user@example.com', $body['userId']);
        $this->assertSame(0, $body['offset']);
        $this->assertSame(3, $body['limit']);
        $this->assertSame(3, $body['total']);
        $this->assertCount(3, $body['files']);
        $this->assertSame('uploads', $body['files'][0]['filename']);
        $this->assertContains('download_folder', $body['files'][0]['actions']);
    }

    public function testFilesFixtureRequiresSessionUser(): void
    {
        $transport = new FakeTransport();
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testFilesFixtureIgnoresQueryParamsAndReturnsFullList(): void
    {
        $transport = new FakeTransport();
        $session = $this->session('session-jwt', 'demo-user@example.com');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/files?offset=0&limit=1&q=fastq', 'offset=0&limit=1&q=fastq'),
            $session,
            '',
        );

        $this->assertSame(0, $transport->calls);
        $body = json_decode($result['body'], true);
        $this->assertSame(200, $result['status']);
        $this->assertSame(0, $body['offset']);
        $this->assertSame(3, $body['limit']);
        $this->assertSame(3, $body['total']);
        $this->assertCount(3, $body['files']);
    }

    public function testFilesFixtureStillRequiresSession(): void
    {
        $transport = new FakeTransport();
        $session = [];
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testFilesFixtureDoesNotShortCircuitNonGet(): void
    {
        $transport = new FakeTransport(200, 'application/json', '{"ok":true}');
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/files', '', 'POST'),
            $session,
            '{"name":"x"}',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame('POST', $transport->method);
        $this->assertSame(200, $result['status']);
        $this->assertSame('{"ok":true}', $result['body']);
    }

    public function testToolsFixtureServesCatalogWithoutCallingApi(): void
    {
        $transport = new FakeTransport();
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/tools'),
            $session,
            '',
        );

        $this->assertSame(0, $transport->calls);
        $this->assertSame(200, $result['status']);
        $this->assertSame('application/json', $result['contentType']);

        $body = json_decode($result['body'], true);
        $this->assertSame(
            [
                [
                    'id' => 'seq',
                    'name' => 'Seq',
                    'dataTypes' => ['seq'],
                ],
                [
                    'id' => 'text',
                    'name' => 'Text',
                    'dataTypes' => ['text'],
                ],
            ],
            $body['tools'],
        );
    }

    public function testToolsFixtureStillRequiresSession(): void
    {
        $transport = new FakeTransport();
        $session = [];
        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, $this->fixturesPath()),
            $this->server('/auth-bff/tools'),
            $session,
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testToolsFixtureDoesNotShortCircuitNonGet(): void
    {
        $transport = new FakeTransport(200, 'application/json', '{"ok":true}');
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(
            new AuthBff($transport, true, $this->fixturesPath()),
            $this->server('/auth-bff/tools', '', 'POST'),
            $session,
            '{"name":"x"}',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame('http://127.0.0.1/api/v1/tools', $transport->url);
        $this->assertSame('POST', $transport->method);
        $this->assertSame(200, $result['status']);
    }

    #[DataProvider('injectionPathProvider')]
    public function testAuthorizedInjectionPathsDoNotCallApi(string $requestUri): void
    {
        $transport = new FakeTransport();
        $session = $this->session('session-jwt');
        $result = $this->invokeAuthBff(new AuthBff($transport), $this->server($requestUri), $session, '');

        $this->assertSame(403, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('FORBIDDEN', json_decode($result['body'], true)['code']);
    }

    public function testExpiredTokenWithNewOidcHeaderRefreshesAndProxies(): void
    {
        $transport = new FakeTransport();
        $session = $this->expiredSession('stale-jwt');
        $server = $this->server('/auth-bff/files');
        $server['OIDC_access_token'] = 'fresh-jwt';
        $server['OIDC_access_token_expires'] = (string) (time() + 3600);

        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, null, new SessionTokenRefresher()),
            $server,
            $session,
            '',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame('Authorization: Bearer fresh-jwt', $this->header($transport->headers, 'Authorization'));
        $this->assertSame(200, $result['status']);
        $this->assertSame('fresh-jwt', $session['userToken']->getToken());
    }

    public function testExpiredTokenWithoutOidcHeaderReturns401(): void
    {
        $transport = new FakeTransport();
        $session = $this->expiredSession('stale-jwt');

        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, null, new SessionTokenRefresher()),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testExpiredTokenWithSameOidcTokenReturns401(): void
    {
        $transport = new FakeTransport();
        $session = $this->expiredSession('stale-jwt');
        $server = $this->server('/auth-bff/files');
        $server['OIDC_access_token'] = 'stale-jwt';

        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, null, new SessionTokenRefresher()),
            $server,
            $session,
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
    }

    public function testApi403RetriesAfterRefreshAndSucceeds(): void
    {
        $transport = new FakeTransport(responses: [
            ['status' => 403, 'body' => '{"code":"FORBIDDEN"}'],
            ['status' => 200, 'body' => '{"files":[]}'],
        ]);
        $refresher = new FakeSessionTokenRefresher([true, true]);

        $session = $this->session('session-jwt');

        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, null, $refresher),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(2, $transport->calls);
        $this->assertSame(2, $refresher->calls);
        $this->assertSame('Authorization: Bearer refreshed-jwt', $this->header($transport->headers, 'Authorization'));
        $this->assertSame(200, $result['status']);
    }

    public function testApi403Returns401WhenRefreshFails(): void
    {
        $transport = new FakeTransport(responses: [
            ['status' => 403, 'body' => '{"code":"FORBIDDEN"}'],
        ]);
        $refresher = new FakeSessionTokenRefresher([true, false]);

        $session = $this->session('session-jwt');

        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, null, $refresher),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(1, $transport->calls);
        $this->assertSame(2, $refresher->calls);
        $this->assertSame(401, $result['status']);
        $this->assertSame('UNAUTHORIZED', json_decode($result['body'], true)['code']);
    }

    public function testRefreshFailureBeforeProxyReturns401(): void
    {
        $transport = new FakeTransport();
        $refresher = new FakeSessionTokenRefresher(false);

        $session = $this->session('session-jwt');

        $result = $this->invokeAuthBff(
            new AuthBff($transport, false, null, $refresher),
            $this->server('/auth-bff/files'),
            $session,
            '',
        );

        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $transport->calls);
        $this->assertSame(1, $refresher->calls);
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
    private function expiredSession(string $accessToken): array
    {
        return [
            'userToken' => new AccessToken([
                'access_token' => $accessToken,
                'expires' => time() - 3600,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function session(string $accessToken, ?string $userId = null): array
    {
        $session = [
            'userToken' => new AccessToken([
                'access_token' => $accessToken,
                'expires' => time() + 3600,
            ]),
        ];
        if ($userId !== null) {
            $session['User'] = ['id' => $userId];
        }

        return $session;
    }

    private function fixturesPath(): string
    {
        return __DIR__ . '/fixtures/workspaceFixtures.json';
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
