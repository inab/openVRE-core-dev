<?php

declare(strict_types=1);

namespace App\Test\Api;

use OpenVREAPI\Controllers\FileController;
use OpenVREAPI\Enums\FileAction;
use OpenVREAPI\Enums\FileKind;
use OpenVREAPI\Services\FileService;
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

final class FakeFilesMetadataCol
{
    /** @param list<array<string, mixed>> $documents */
    public function __construct(private array $documents = [])
    {
    }

    public function find(array $filter, array $options = []): FakeFilesCursor
    {
        $ids = $filter['_id']['$in'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return new FakeFilesCursor([]);
        }

        $page = array_values(array_filter(
            $this->documents,
            static fn (array $doc): bool => in_array($doc['_id'] ?? null, $ids, true),
        ));

        return new FakeFilesCursor($page);
    }
}

final class FileControllerTest extends TestCase
{
    private const DEFAULT_PROJECTION = [
        '_id' => 1,
        'files' => 1,
        'mtime' => 1,
        'parentDir' => 1,
        'path' => 1,
        'project' => 1,
        'size' => 1,
        'type' => 1,
    ];

    private const OWNER_ID = 'internal-user';

    private const ACTIVE_PROJECT = '__PROJ1';

    private const DATA_DIR = 'project-folder-id';

    public function testListReturnsNotFoundWhenUserIsMissing(): void
    {
        $usersCol = new FakeUsersCol(null);
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($usersCol, $filesCol)->list(
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

        $response = $this->controller($usersCol, $filesCol)->list(
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

    public function testListReturnsNotFoundWhenUserHasNoActiveProject(): void
    {
        $usersCol = new FakeUsersCol(['id' => self::OWNER_ID, 'dataDir' => self::DATA_DIR]);
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($usersCol, $filesCol)->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertNull($filesCol->lastCountFilter);
        $this->assertNull($filesCol->lastFindFilter);
        $this->assertSame(
            [
                'code' => 'NOT_FOUND',
                'status' => 404,
                'message' => 'Active project not found',
            ],
            $this->json($response),
        );
    }

    public function testListReturnsFullListWhenPagingParamsAreOmitted(): void
    {
        $page = [
            ['_id' => 'f1', 'path' => 'a.txt', 'type' => 'file', 'size' => 12],
            ['_id' => 'f2', 'path' => 'b.txt', 'type' => 'file', 'size' => 4],
        ];
        $filesCol = new FakeFilesCol(2, $page);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($this->projectFilter(), $filesCol->lastCountFilter);
        $this->assertSame($this->projectFilter(), $filesCol->lastFindFilter);
        $this->assertSame(
            [
                'projection' => self::DEFAULT_PROJECTION,
                'sort' => ['path' => 1],
            ],
            $filesCol->lastFindOptions,
        );
        $this->assertArrayNotHasKey('skip', $filesCol->lastFindOptions);
        $this->assertArrayNotHasKey('limit', $filesCol->lastFindOptions);

        $body = $this->json($response);
        $this->assertSame('user@example.com', $body['userId']);
        $this->assertSame(0, $body['offset']);
        $this->assertSame(2, $body['limit']);
        $this->assertSame(2, $body['total']);
        $this->assertCount(2, $body['files']);
        $this->assertSame('f1', $body['files'][0]['fileId']);
        $this->assertSame('a.txt', $body['files'][0]['filename']);
        $this->assertSame('f2', $body['files'][1]['fileId']);
        $this->assertSame('b.txt', $body['files'][1]['filename']);
        $this->assertFileContract($body['files'][0]);
        $this->assertFileContract($body['files'][1]);
    }

    public function testListMapsMetadataValidatedToStatus(): void
    {
        $page = [
            ['_id' => 'ready-file', 'path' => 'ready.txt', 'type' => 'file', 'size' => 1],
            ['_id' => 'unvalidated-file', 'path' => 'raw.txt', 'type' => 'file', 'size' => 2],
        ];
        $filesCol = new FakeFilesCol(2, $page);
        $metadataCol = new FakeFilesMetadataCol([
            ['_id' => 'ready-file', 'validated' => true],
            ['_id' => 'unvalidated-file', 'validated' => false],
        ]);

        $response = $this->controller($this->userCol(), $filesCol, $metadataCol)->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->json($response);
        $this->assertCount(2, $body['files']);
        $this->assertFileContract($body['files'][0]);
        $this->assertFileContract($body['files'][1]);
        $this->assertSame('ready', $body['files'][0]['status']);
        $this->assertSame('unvalidated', $body['files'][1]['status']);
    }

    public function testListAppliesPaginationAndReturnsTotal(): void
    {
        $page = [['_id' => 'f1', 'path' => 'a.txt', 'type' => 'file', 'size' => 12]];
        $filesCol = new FakeFilesCol(3, $page);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['offset' => '50', 'limit' => '25']),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($this->projectFilter(), $filesCol->lastCountFilter);
        $this->assertSame($this->projectFilter(), $filesCol->lastFindFilter);
        $this->assertSame(
            [
                'projection' => self::DEFAULT_PROJECTION,
                'sort' => ['path' => 1],
                'skip' => 50,
                'limit' => 25,
            ],
            $filesCol->lastFindOptions,
        );

        $body = $this->json($response);
        $this->assertSame('user@example.com', $body['userId']);
        $this->assertSame(50, $body['offset']);
        $this->assertSame(25, $body['limit']);
        $this->assertSame(3, $body['total']);
        $this->assertCount(1, $body['files']);
        $this->assertSame('f1', $body['files'][0]['fileId']);
    }

    public function testListUsesDefaultLimitWhenOnlyOffsetIsProvided(): void
    {
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['offset' => '10']),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(10, $filesCol->lastFindOptions['skip']);
        $this->assertSame(50, $filesCol->lastFindOptions['limit']);
        $this->assertSame(10, $this->json($response)['offset']);
        $this->assertSame(50, $this->json($response)['limit']);
    }

    public function testListUsesDefaultOffsetWhenOnlyLimitIsProvided(): void
    {
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['limit' => '25']),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $filesCol->lastFindOptions['skip']);
        $this->assertSame(25, $filesCol->lastFindOptions['limit']);
        $this->assertSame(0, $this->json($response)['offset']);
        $this->assertSame(25, $this->json($response)['limit']);
    }

    public function testListCapsLimitAtMaximum(): void
    {
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['limit' => '1000000']),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(200, $filesCol->lastFindOptions['limit']);
        $this->assertSame(0, $filesCol->lastFindOptions['skip']);
        $this->assertSame(200, $this->json($response)['limit']);
    }

    public function testListAppliesPathSearchBeforePagination(): void
    {
        $page = [['_id' => 'f1', 'path' => 'uploads/readme.txt', 'type' => 'file', 'size' => 4]];
        $filesCol = new FakeFilesCol(1, $page);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', [
                'offset' => '10',
                'limit' => '25',
                'q' => 'readme.txt',
            ]),
            new Response(),
            [],
        );

        $expectedFilter = [
            'owner' => self::OWNER_ID,
            'project' => self::ACTIVE_PROJECT,
            '_id' => ['$ne' => self::DATA_DIR],
            '$and' => [
                ['path' => ['$ne' => self::OWNER_ID]],
                ['path' => [
                    '$regex' => preg_quote('readme.txt', '/'),
                    '$options' => 'i',
                ]],
            ],
        ];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($expectedFilter, $filesCol->lastCountFilter);
        $this->assertSame($expectedFilter, $filesCol->lastFindFilter);
        $this->assertSame(10, $filesCol->lastFindOptions['skip']);
        $this->assertSame(25, $filesCol->lastFindOptions['limit']);

        $body = $this->json($response);
        $this->assertSame(10, $body['offset']);
        $this->assertSame(25, $body['limit']);
        $this->assertSame(1, $body['total']);
        $this->assertSame('f1', $body['files'][0]['fileId']);
        $this->assertSame('readme.txt', $body['files'][0]['filename']);
    }

    public function testListIgnoresEmptyOrWhitespaceSearch(): void
    {
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['q' => "  \t  "]),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($this->projectFilter(), $filesCol->lastCountFilter);
        $this->assertSame($this->projectFilter(), $filesCol->lastFindFilter);
        $this->assertArrayNotHasKey('skip', $filesCol->lastFindOptions);
        $this->assertArrayNotHasKey('limit', $filesCol->lastFindOptions);
    }

    public function testListIgnoresNonStringSearch(): void
    {
        $filesCol = new FakeFilesCol(0, []);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['q' => ['readme.txt']]),
            new Response(),
            [],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($this->projectFilter(), $filesCol->lastCountFilter);
        $this->assertSame($this->projectFilter(), $filesCol->lastFindFilter);
    }

    public function testListTruncatesLongSearch(): void
    {
        $filesCol = new FakeFilesCol(0, []);

        $q = str_repeat('a', 250);
        $truncated = str_repeat('a', 200);

        $response = $this->controller($this->userCol(), $filesCol)->list(
            $this->request('user@example.com', ['q' => $q]),
            new Response(),
            [],
        );

        $expectedFilter = [
            'owner' => self::OWNER_ID,
            'project' => self::ACTIVE_PROJECT,
            '_id' => ['$ne' => self::DATA_DIR],
            '$and' => [
                ['path' => ['$ne' => self::OWNER_ID]],
                ['path' => [
                    '$regex' => preg_quote($truncated, '/'),
                    '$options' => 'i',
                ]],
            ],
        ];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($expectedFilter, $filesCol->lastCountFilter);
        $this->assertSame($expectedFilter, $filesCol->lastFindFilter);
    }

    public function testListScopesToActiveProjectWithoutExcludingRootWhenDataDirMissing(): void
    {
        $filesCol = new FakeFilesCol(0, []);
        $usersCol = new FakeUsersCol([
            'id' => self::OWNER_ID,
            'activeProject' => self::ACTIVE_PROJECT,
        ]);

        $response = $this->controller($usersCol, $filesCol)->list(
            $this->request('user@example.com'),
            new Response(),
            [],
        );

        $expectedFilter = [
            'owner' => self::OWNER_ID,
            'project' => self::ACTIVE_PROJECT,
            'path' => ['$ne' => self::OWNER_ID],
        ];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($expectedFilter, $filesCol->lastCountFilter);
        $this->assertSame($expectedFilter, $filesCol->lastFindFilter);
    }

    private function controller(
        FakeUsersCol $usersCol,
        FakeFilesCol $filesCol,
        ?FakeFilesMetadataCol $metadataCol = null,
    ): FileController {
        return new FileController(new FileService(
            $filesCol,
            $metadataCol ?? new FakeFilesMetadataCol(),
            $usersCol,
        ));
    }

    /**
     * @param array<string, mixed> $file
     */
    private function assertFileContract(array $file): void
    {
        $kinds = array_map(static fn (FileKind $kind): string => $kind->value, FileKind::cases());
        $actions = array_map(static fn (FileAction $action): string => $action->value, FileAction::cases());

        $this->assertContains($file['kind'], $kinds);
        $this->assertContains($file['status'], ['ready', 'unvalidated']);
        $this->assertIsArray($file['actions']);
        foreach ($file['actions'] as $action) {
            $this->assertContains($action, $actions);
        }
    }

    private function userCol(): FakeUsersCol
    {
        return new FakeUsersCol([
            'id' => self::OWNER_ID,
            'activeProject' => self::ACTIVE_PROJECT,
            'dataDir' => self::DATA_DIR,
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function projectFilter(array $extra = []): array
    {
        return array_merge([
            'owner' => self::OWNER_ID,
            'project' => self::ACTIVE_PROJECT,
            'path' => ['$ne' => self::OWNER_ID],
            '_id' => ['$ne' => self::DATA_DIR],
        ], $extra);
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
