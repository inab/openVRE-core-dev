<?php

declare(strict_types=1);

namespace OpenVREAPI\Mappers;

use MongoDB\BSON\UTCDateTime;
use OpenVREAPI\OpenApi\Schemas\FileDto;
use OpenVREAPI\Enums\FileAction;
use OpenVREAPI\Enums\FileKind;


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
            kind: $kind,
            status: self::getFileStatus($doc),
            actions: $kind ? self::getFileActions($kind) : []
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

    private static function getFileKind(array $doc): FileKind
    {
        return $doc['validated'] === true ? FileKind::File : FileKind::FileUnvalidated;
    }

    private static function getDirectoryKind(array $doc): FileKind
    {
        $uploadsFolder = 'uploads';
        $repositoryFolder = 'repository';
        if (basename($doc['path']) === $uploadsFolder && $doc['project'] === basename(dirname($doc['path']))) {
            return FileKind::FolderUploads;
        }

        if (basename($doc['path']) === $repositoryFolder && $doc['project'] === basename(dirname($doc['path']))) {
            return FileKind::FolderRepository;
        }

        if ($doc['files'] === []) {
            return FileKind::FolderEmpty;
        }

        return FileKind::Folder;
    }

    private static function getFileStatus(array $doc): string
    {
        return $doc['validated'] || is_null($doc['validated']) ? 'ready' : 'unvalidated';
    }

    private static function getFileActions(FileKind $kind): array
    {
        return array_map(fn(FileAction $a) => $a->value, $kind->actions());
    }
}
