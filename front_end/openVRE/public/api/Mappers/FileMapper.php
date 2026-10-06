<?php

declare(strict_types=1);

namespace OpenVREAPI\Mappers;

use MongoDB\BSON\UTCDateTime;
use OpenVREAPI\OpenApi\Schemas\FileDto;


final class FileMapper
{
    public static function toFileItem(array $doc): FileDto
    {
        $type = self::resolveType($doc);

        return new FileDto(
            fileId: (string) ($doc['_id'] ?? ''),
            filename: basename($doc['path'] ?? '') ?: '',
            format: $doc['format'] ?? '',
            type: $type,
            dataType: $doc['data_type'] ?? '',
            date: self::mongoDateToIso($doc['mtime'] ?? null),
            size: (int) ($doc['size'] ?? 0),
            path: $doc['path'] ?? '',
            parentId: $doc['parentDir'] ?? null,
            kind: $type === 'file' ? self::getFileKind($doc) : self::getDirectoryKind($doc),
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

    private static function resolveType(array $doc): string
    {
        if (($doc['type'] ?? '') === 'dir' || ($doc['type'] ?? '') === 'file') {
            return $doc['type'];
        }

        return array_key_exists('files', $doc) ? 'dir' : 'file';
    }

    private static function getFileKind(array $doc): string
    {
        if (!self::existsOnDisk($doc['path'] ?? '')) {
            return 'file_unavailable';
        }

        return !empty($doc['validated']) ? 'file' : 'file_unvalidated';
    }

    private static function existsOnDisk(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/') {
            return file_exists($path);
        }

        $dataDir = $GLOBALS['dataDir'] ?? '/shared_data/userdata';
        $absolutePath = rtrim((string) $dataDir, '/') . '/' . ltrim($path, '/');

        return file_exists($absolutePath);
    }

    private static function getDirectoryKind(array $doc): string
    {
        $path = $doc['path'] ?? '';

        if (!self::existsOnDisk($path)) {
            return 'folder_unavailable';
        }

        $basename = basename($path);
        $project = $doc['project'] ?? null;
        $parentName = basename(dirname($path));

        if ($basename === 'uploads' && $project === $parentName) {
            return 'folder_uploads';
        }

        if ($basename === 'repository' && $project === $parentName) {
            return 'folder_repository';
        }

        if (($doc['files'] ?? null) === []) {
            return 'folder_empty';
        }

        return 'folder';
    }
}
