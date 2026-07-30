<?php

declare(strict_types=1);

namespace OpenVREAPI\Middleware;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;
use Exception;

/**
 * Mandatory Bearer token authentication using JWKS-based JWT verification.
 *
 * Every request must include a valid "Authorization: Bearer <token>" header.
 * - Missing/malformed header  -> 401 Unauthorized
 * - Present but invalid token -> 403 Forbidden
 *
 * The JWKS (JSON Web Key Set) is expected to have been fetched from the
 * auth server's JWKS endpoint ahead of time and stored at $jwksFilePath.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $jwksFilePath = __DIR__ . '/../../.jwks',
        private readonly string $userIdClaim = 'sub'
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            $token = $this->getBearerToken($request->getHeaderLine('Authorization'));
        } catch (Exception $e) {
            return $this->jsonError(401, 'UNAUTHORIZED', $e->getMessage());
        }

        try {
            $decoded = $this->validateToken($token);
        } catch (Exception $e) {
            return $this->jsonError(403, 'FORBIDDEN', $e->getMessage());
        }

        $userId = $decoded->{$this->userIdClaim} ?? null;

        if ($userId === null) {
            return $this->jsonError(403, 'FORBIDDEN', sprintf('Token is missing required claim "%s"', $this->userIdClaim));
        }

        // Make the decoded token payload — and the userId derived from it —
        // available to downstream handlers. Routes no longer take userId
        // from the URL; it always comes from the authenticated token.
        $request = $request->withAttribute('authToken', $token);
        $request = $request->withAttribute('authClaims', $decoded);
        $request = $request->withAttribute('userId', (string) $userId);

        return $handler->handle($request);
    }

    /**
     * Extracts the bearer token from the Authorization header.
     *
     * @throws Exception if the header is missing or not a well-formed Bearer header.
     */
    private function getBearerToken(string $authHeader): string
    {
        if (empty($authHeader)) {
            throw new Exception('Authorization header not found');
        }

        $matchedBearer = preg_match('/^Bearer\s(\S+)$/', $authHeader, $bearerText);

        if ($matchedBearer === 0) {
            throw new Exception('Bearer authorization header not found');
        }

        if ($matchedBearer === false) {
            throw new Exception('Error parsing authorization header');
        }

        return $bearerText[1];
    }

    /**
     * Validates the JWT against the JWKS key set.
     *
     * @return object Decoded token claims.
     * @throws Exception if the JWKS file can't be read or the token is invalid/expired.
     */
    private function validateToken(string $token): object
    {
        try {
            $jwks = json_decode(file_get_contents($this->jwksFilePath), true, 512, JSON_THROW_ON_ERROR);
            $parsedKeySet = JWK::parseKeySet($jwks);

            return JWT::decode($token, $parsedKeySet);
        } catch (Exception $e) {
            throw new Exception('Invalid token: ' . $e->getMessage());
        }
    }

    private function jsonError(int $status, string $code, string $message): Response
    {
        $response = new SlimResponse();
        $payload = json_encode([
            'code' => $code,
            'status' => $status,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
