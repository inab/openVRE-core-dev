<?php

declare(strict_types=1);

namespace OpenVREAPI\Mappers;

use MongoDB\BSON\UTCDateTime;
use OpenVREAPI\OpenApi\Schemas\FileDto;


final class FileMapper
{
    public static function toFileItem(array $doc): FileDto
    {
        if (isset($doc['type'])) {
            $kind = $doc['type'] === 'file' ? self::getFileKind($doc) : self::getDirectoryKind($doc);
        }

        return new FileDto(
            fileId: (string) ($doc['_id'] ?? ''),
            filename: basename($doc['path']) ?? '',
            format: $doc['format'] ?? '',
            type: $doc['type'] ?? '',
            dataType: $doc['data_type'] ?? '',
            date: self::mongoDateToIso($doc['mtime'] ?? null),
            size: (int) ($doc['size'] ?? 0),
            path: $doc['path'] ?? '',
            parentId: $doc['parentDir'] ?? null,
            kind: $kind ?? ''
        );
    }

    /** @return FileDto[] */
    public static function toFileItems(array $docs): array
    {
        return array_map([self::class, 'toFileItem'], $docs);
    }

    private static function mongoDateToIso(?UTCDateTime $date): string
    {
        return $date !== null ? $date->toDateTime()->format('Y-m-d\TH:i:s.v') . '+00:00' : '';
    }

    private static function getFileKind(array $doc): string
    {
        return $doc['validated'] === true ? 'file' : 'file_unvalidated';
    }

    private static function getDirectoryKind(array $doc): string
    {
        if (basename($doc['path']) === 'uploads' && $doc['project'] === basename(dirname($doc['path']))) {
            return 'folder_uploads';
        }

        if (basename($doc['path']) === 'repository' && $doc['project'] === basename(dirname($doc['path']))) {
            return 'folder_repository';
        }

        if ($doc['files'] === []) {
            return 'folder_empty';
        }

        return 'folder';
    }
}
