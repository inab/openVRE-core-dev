<?php

declare(strict_types=1);

namespace App\Test\Api;

use OpenVREAPI\Controllers\FileController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class FakeUsersCol
{
    public ?array $lastFilter = null;

    public function __construct(private mixed $document)
    {
    }

    public function findOne(array $filter, array $options = []): mixed
    {
        $this->lastFilter = $filter;

        return $this->document;
    }
}

final class FakeFilesCursor
{
    /** @param list<array<string, mixed>> $documents */
    public function __construct(private array $documents)
    {
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->documents;
    }
}

final class FakeFilesCol
{
    public ?array $lastCountFilter = null;

    public ?array $lastFindFilter = null;

    public ?array $lastFindOptions = null;

    /** @param list<array<string, mixed>> $page */
    public function __construct(private int $total, private array $page)
    {
    }

    public function countDocuments(array $filter): int
    {
        $this->lastCountFilter = $filter;

        return $this->total;
    }

    public function find(array $filter, array $options = []): FakeFilesCursor
    {
        $this->lastFindFilter = $filter;
        $this->lastFindOptions = $options;

        return new FakeFilesCursor($this->page);
    }
}

final class FileControllerTest extends TestCase
{
    private mixed $previousUsersCol;

    private mixed $previousFilesCol;

    private bool $hadUsersCol;

    private bool $hadFilesCol;

    protected function setUp(): void
    {
        $this->hadUsersCol = array_key_exists('usersCol', $GLOBALS);
        $this->hadFilesCol = array_key_exists('filesCol', $GLOBALS);
        $this->previousUsersCol = $GLOBALS['usersCol'] ?? null;
        $this->previousFilesCol = $GLOBALS['filesCol'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadUsersCol) {
            $GLOBALS['usersCol'] = $this->previousUsersCol;
        } else {
            unset($GLOBALS['usersCol']);
        }

        if ($this->hadFilesCol) {
            $GLOBALS['filesCol'] = $this->previousFilesCol;
        } else {
            unset($GLOBALS['filesCol']);
        }
    }

    public function testListReturnsNotFoundWhenUserIsMissing(): void
    {
        $usersCol = new FakeUsersCol(null);
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = $usersCol;
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['_id' => 'user@example.com'], $usersCol->lastFilter);
        $this->assertNull($filesCol->lastCountFilter);
        $this->assertNull($filesCol->lastFindFilter);
        $this->assertSame(
            [
                'code' => 'NOT_FOUND',
                'status' => 404,
                'message' => 'User not found',
            ],
            $this->json($response),
        );
    }

    public function testListReturnsNotFoundWhenUserHasNoId(): void
    {
        $usersCol = new FakeUsersCol([]);
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = $usersCol;
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['_id' => 'user@example.com'], $usersCol->lastFilter);
        $this->assertNull($filesCol->lastCountFilter);
        $this->assertNull($filesCol->lastFindFilter);
        $this->assertSame(
            [
                'code' => 'NOT_FOUND',
                'status' => 404,
                'message' => 'User not found',
            ],
            $this->json($response),
        );
    }

    public function testListAppliesPaginationAndReturnsTotal(): void
    {
        $page = [['path' => 'a.txt', 'type' => 'file', 'size' => 12]];
        $usersCol = new FakeUsersCol(['id' => 'internal-user']);
        $filesCol = new FakeFilesCol(3, $page);
        $GLOBALS['usersCol'] = $usersCol;
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com', ['offset' => '50', 'limit' => '25']),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['owner' => 'internal-user'], $filesCol->lastCountFilter);
        $this->assertSame(['owner' => 'internal-user'], $filesCol->lastFindFilter);
        $this->assertSame(
            [
                'projection' => ['atime' => 1, 'files' => 1, 'path' => 1, 'size' => 1, 'type' => 1],
                'sort' => ['path' => 1],
                'skip' => 50,
                'limit' => 25,
            ],
            $filesCol->lastFindOptions,
        );
        $this->assertSame(
            [
                'userId' => 'user@example.com',
                'offset' => 50,
                'limit' => 25,
                'total' => 3,
                'files' => $page,
            ],
            $this->json($response),
        );
    }

    public function testListUsesDefaultOffsetAndLimit(): void
    {
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = new FakeUsersCol(['id' => 'internal-user']);
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $filesCol->lastFindOptions['skip']);
        $this->assertSame(50, $filesCol->lastFindOptions['limit']);
        $this->assertSame(0, $this->json($response)['offset']);
        $this->assertSame(50, $this->json($response)['limit']);
        $this->assertSame(0, $this->json($response)['total']);
    }

    public function testListCapsLimitAtMaximum(): void
    {
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = new FakeUsersCol(['id' => 'internal-user']);
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com', ['limit' => '1000000']),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(100, $filesCol->lastFindOptions['limit']);
        $this->assertSame(100, $this->json($response)['limit']);
    }

    public function testListAppliesPathSearchBeforePagination(): void
    {
        $page = [['path' => 'uploads/readme.txt', 'type' => 'file', 'size' => 4]];
        $filesCol = new FakeFilesCol(1, $page);
        $GLOBALS['usersCol'] = new FakeUsersCol(['id' => 'internal-user']);
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com', [
                'offset' => '10',
                'limit' => '25',
                'q' => 'readme.txt',
            ]),
            new Response(),
            [],
        );

        $expectedFilter = [
            'owner' => 'internal-user',
            'path' => [
                '$regex' => preg_quote('readme.txt', '/'),
                '$options' => 'i',
            ],
        ];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($expectedFilter, $filesCol->lastCountFilter);
        $this->assertSame($expectedFilter, $filesCol->lastFindFilter);
        $this->assertSame(10, $filesCol->lastFindOptions['skip']);
        $this->assertSame(25, $filesCol->lastFindOptions['limit']);
        $this->assertSame(
            [
                'userId' => 'user@example.com',
                'offset' => 10,
                'limit' => 25,
                'total' => 1,
                'files' => $page,
            ],
            $this->json($response),
        );
    }

    public function testListIgnoresEmptyOrWhitespaceSearch(): void
    {
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = new FakeUsersCol(['id' => 'internal-user']);
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com', ['q' => "  \t  "]),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['owner' => 'internal-user'], $filesCol->lastCountFilter);
        $this->assertSame(['owner' => 'internal-user'], $filesCol->lastFindFilter);
    }

    public function testListIgnoresNonStringSearch(): void
    {
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = new FakeUsersCol(['id' => 'internal-user']);
        $GLOBALS['filesCol'] = $filesCol;

        $response = (new FileController())->list(
            $this->request('user@example.com', ['q' => ['readme.txt']]),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['owner' => 'internal-user'], $filesCol->lastCountFilter);
        $this->assertSame(['owner' => 'internal-user'], $filesCol->lastFindFilter);
    }

    public function testListTruncatesLongSearch(): void
    {
        $filesCol = new FakeFilesCol(0, []);
        $GLOBALS['usersCol'] = new FakeUsersCol(['id' => 'internal-user']);
        $GLOBALS['filesCol'] = $filesCol;

        $q = str_repeat('a', 250);
        $truncated = str_repeat('a', 200);

        $response = (new FileController())->list(
            $this->request('user@example.com', ['q' => $q]),
            new Response(),
            [],
        );

        $expectedFilter = [
            'owner' => 'internal-user',
            'path' => [
                '$regex' => preg_quote($truncated, '/'),
                '$options' => 'i',
            ],
        ];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($expectedFilter, $filesCol->lastCountFilter);
        $this->assertSame($expectedFilter, $filesCol->lastFindFilter);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function request(string $userId, array $query = []): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('GET', '/files')
            ->withQueryParams($query)
            ->withAttribute('userId', $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
