<?php

declare(strict_types=1);

namespace OpenVREAPI\Services;

use MongoDB\Client as MongoClient;
use OpenVREAPI\Mappers\FileMapper;
use OpenVREAPI\OpenApi\Schemas\FileDto;
use RuntimeException;

final class FileService
{
    private object $filesCollection;
    private object $filesMetadataCollection;
    private object $usersCollection;

    public function __construct(
        ?object $filesCollection = null,
        ?object $filesMetadataCollection = null,
        ?object $usersCollection = null,
    ) {
        if ($filesCollection !== null && $filesMetadataCollection !== null && $usersCollection !== null) {
            $this->filesCollection = $filesCollection;
            $this->filesMetadataCollection = $filesMetadataCollection;
            $this->usersCollection = $usersCollection;

            return;
        }

        $connectionUri = 'mongodb://' . getenv('MONGO_CREDENTIALS') . '@' . getenv('MONGO_SERVER') . '/?authSource=' . getenv('MONGO_MAIN_DB');

        $mongoClient = new MongoClient($connectionUri, [
            'readConcernLevel' => 'local',
        ], [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ]);

        $database = $mongoClient->selectDatabase(getenv('MONGO_MAIN_DB'));
        $this->filesCollection = $filesCollection ?? $database->selectCollection('files');
        $this->filesMetadataCollection = $filesMetadataCollection ?? $database->selectCollection('filesMetadata');
        $this->usersCollection = $usersCollection ?? $database->selectCollection('users');
    }

    /**
     * @return array{files: list<FileDto>, total: int}
     * @throws RuntimeException when the user document is missing or has no owner id
     */
    public function findByUserId(string $userId, ?int $offset = null, ?int $limit = null, string $q = ''): array
    {
        $userDoc = $this->usersCollection->findOne(['_id' => $userId], ['projection' => ['id' => 1]]);
        if ($userDoc === null) {
            throw new RuntimeException('User not found', 404);
        }

        $ownerId = $userDoc['id'] ?? null;
        if ($ownerId === null || $ownerId === '') {
            throw new RuntimeException('User not found', 404);
        }

        $filter = ['owner' => $ownerId];
        if ($q !== '') {
            $filter['path'] = [
                '$regex' => preg_quote($q, '/'),
                '$options' => 'i',
            ];
        }

        $total = $this->filesCollection->countDocuments($filter);
        $findOptions = [
            'projection' => [
                '_id' => 1,
                'files' => 1,
                'mtime' => 1,
                'parentDir' => 1,
                'path' => 1,
                'project' => 1,
                'size' => 1,
                'type' => 1,
            ],
            'sort' => ['path' => 1],
        ];
        if ($offset !== null) {
            $findOptions['skip'] = $offset;
        }
        if ($limit !== null) {
            $findOptions['limit'] = $limit;
        }

        $fileDocs = $this->filesCollection->find($filter, $findOptions)->toArray();

        $fileIds = array_column($fileDocs, '_id');
        $filesMetadataDocs = $fileIds === []
            ? []
            : $this->filesMetadataCollection->find(['_id' => ['$in' => $fileIds]], [
                'projection' => ['data_type' => 1, 'description' => 1, 'format' => 1, 'validated' => 1],
            ])->toArray();
        $metadataById = array_column($filesMetadataDocs, null, '_id');

        $files = array_map(function ($doc) use ($metadataById) {
            $merged = array_merge($doc, $metadataById[$doc['_id']] ?? []);

            return FileMapper::toFileItem($merged);
        }, $fileDocs);

        return [
            'files' => $files,
            'total' => $total,
        ];
    }

    /**
     * @return list<FileDto>
     * @deprecated Prefer findByUserId() which returns total and supports optional paging/q
     */
    public function findPaginatedByUserId(string $userId, int $offset, int $limit): array
    {
        return $this->findByUserId($userId, $offset, $limit)['files'];
    }
}
