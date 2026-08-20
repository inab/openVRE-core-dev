<?php

declare(strict_types=1);

namespace OpenVREAPI\Services;

use MongoDB\Client as MongoClient;
use MongoDB\Collection;
use OpenVREAPI\Mappers\FileMapper;
use OpenVREAPI\OpenApi\Schemas\FileDto;

final class FileService
{
    private ?MongoClient $mongoClient = null;
    private Collection $filesCollection;
    private Collection $filesMetadataCollection;
    private Collection $usersCollection;


    public function __construct()
    {
        $connectionUri = "mongodb://" . getenv('MONGO_CREDENTIALS') . "@" . getenv('MONGO_SERVER') . "/?authSource=" . getenv('MONGO_MAIN_DB');

        $this->mongoClient = new MongoClient($connectionUri, array(
            'readConcernLevel' => 'local'
        ), array(
            'typeMap' => array(
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array'
            )
        ));

        $this->filesCollection = $this->mongoClient->selectDatabase(getenv('MONGO_MAIN_DB'))->selectCollection('files');
        $this->filesMetadataCollection = $this->mongoClient->selectDatabase(getenv('MONGO_MAIN_DB'))->selectCollection('filesMetadata');
        $this->usersCollection = $this->mongoClient->selectDatabase(getenv('MONGO_MAIN_DB'))->selectCollection('users');
    }


    /**
     * @return array{files: FileDto[], total: int}
     */
    public function findPaginatedByUserId(string $userId, ?int $offset = null, ?int $limit = null, string $q = ''): array
    {
        $userDoc = $this->usersCollection->findOne(['_id' => $userId], ['projection' => ['id' => 1]]);
        $filter = ['owner' => $userDoc['id']];
        if ($q !== '') {
            $filter['path'] = [
                '$regex' => preg_quote($q, '/'),
                '$options' => 'i',
            ];
        }

        $total = $this->filesCollection->countDocuments($filter);
        $findOptions = [
            'projection' => ['_id' => 1, 'files' => 1, 'mtime' => 1, 'parentDir' => 1, 'path' => 1, 'project' => 1, 'size' => 1, 'type' => 1],
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
        $filesMetadataDocs = $this->filesMetadataCollection->find(['_id' => ['$in' => $fileIds]], [
            'projection' => ['data_type' => 1, 'description' => 1, 'format' => 1, 'validated' => 1]
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
}
