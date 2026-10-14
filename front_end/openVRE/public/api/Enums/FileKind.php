<?php

namespace OpenVREAPI\Enums;

enum FileKind: string
{
    case File = 'file';
    case FileUnvalidated = 'file_unvalidated';
    case Folder = 'folder';
    case FolderUploads = 'folder_uploads';
    case FolderRepository = 'folder_repository';
    case FolderEmpty = 'folder_empty';

    /** @return FileAction[] */
    public function actions(): array
    {
        return match ($this) {
            self::File, self::FileUnvalidated => [
                FileAction::Compress,
                FileAction::Delete,
                FileAction::Download,
                FileAction::EditMetadata,
                FileAction::Move,
                FileAction::Rename,
                FileAction::ValidateMetadata,
            ],
            self::Folder => [
                FileAction::Compress,
                FileAction::DeleteFolder,
                FileAction::DownloadFolder,
                FileAction::EditMetadata,
                FileAction::Move,
                FileAction::Rename,
                FileAction::ValidateMetadata,
            ],
            self::FolderUploads => [FileAction::Compress, FileAction::DownloadFolder],
            self::FolderRepository => [],
            self::FolderEmpty => [
                FileAction::DeleteFolder,
                FileAction::EditMetadata,
                FileAction::Move,
                FileAction::Rename,
                FileAction::ValidateMetadata,
            ],
        };
    }
}
