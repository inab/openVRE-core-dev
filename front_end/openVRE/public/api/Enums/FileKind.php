<?php

declare(strict_types=1);

namespace OpenVREAPI\Enums;

enum FileKind: string
{
    case File = 'file';
    case FileUnvalidated = 'file_unvalidated';
    case FileUnavailable = 'file_unavailable';
    case Folder = 'folder';
    case FolderUploads = 'folder_uploads';
    case FolderRepository = 'folder_repository';
    case FolderEmpty = 'folder_empty';
    case FolderUnavailable = 'folder_unavailable';

    /** @return FileAction[] */
    public function actions(): array
    {
        return match ($this) {
            self::File => [
                FileAction::EditMetadata,
                FileAction::Rename,
                FileAction::Move,
                FileAction::Download,
                FileAction::Delete,
                FileAction::Compress,
            ],
            self::FileUnvalidated => [
                FileAction::ValidateMetadata,
                FileAction::Rename,
                FileAction::Move,
                FileAction::Delete,
            ],
            self::FileUnavailable => [
                FileAction::Delete,
            ],
            self::Folder => [
                FileAction::Rename,
                FileAction::Move,
                FileAction::DeleteFolder,
                FileAction::DownloadFolder,
            ],
            self::FolderEmpty => [
                FileAction::DeleteFolder,
                FileAction::DownloadFolder,
            ],
            self::FolderUnavailable => [
                FileAction::DeleteFolder,
            ],
            self::FolderUploads => [
                FileAction::DownloadFolder,
            ],
            self::FolderRepository => [
                FileAction::DownloadFolder,
            ],
        };
    }
}
