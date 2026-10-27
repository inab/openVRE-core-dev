<?php

declare(strict_types=1);

namespace OpenVREAPI\Mappers;

use MongoDB\BSON\UTCDateTime;
use OpenVREAPI\Enums\FileAction;
use OpenVREAPI\Enums\FileKind;
use OpenVREAPI\OpenApi\Schemas\FileDto;

final class FileMapper
{
    public static function toFileItem(array $doc): FileDto
    {
        $type = self::resolveType($doc);
        $kind = $type === 'file' ? self::getFileKind($doc) : self::getDirectoryKind($doc);

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
            kind: $kind,
            status: self::getFileStatus($doc),
            actions: self::getFileActions($kind),
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

    private static function getFileKind(array $doc): FileKind
    {
        if (!self::existsOnDisk($doc['path'] ?? '')) {
            return FileKind::FileUnavailable;
        }

        return !empty($doc['validated']) ? FileKind::File : FileKind::FileUnvalidated;
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

    private static function getDirectoryKind(array $doc): FileKind
    {
        $path = $doc['path'] ?? '';

        if (!self::existsOnDisk($path)) {
            return FileKind::FolderUnavailable;
        }

        $basename = basename($path);
        $project = $doc['project'] ?? null;
        $parentName = basename(dirname($path));

        if ($basename === 'uploads' && $project === $parentName) {
            return FileKind::FolderUploads;
        }

        if ($basename === 'repository' && $project === $parentName) {
            return FileKind::FolderRepository;
        }

        if (($doc['files'] ?? null) === []) {
            return FileKind::FolderEmpty;
        }

        return FileKind::Folder;
    }

    private static function getFileStatus(array $doc): string
    {
        $validated = $doc['validated'] ?? null;

        return $validated || $validated === null ? 'ready' : 'unvalidated';
    }

    /** @return list<string> */
    private static function getFileActions(FileKind $kind): array
    {
        return array_map(static fn (FileAction $action) => $action->value, $kind->actions());
    }
}
