<?php

declare(strict_types=1);

namespace OpenVREAPI\Enums;

enum FileAction: string
{
    case Compress = 'compress';
    case Delete = 'delete';
    case DeleteFolder = 'delete_folder';
    case Download = 'download';
    case DownloadFolder = 'download_folder';
    case EditMetadata = 'edit_metadata';
    case Move = 'move';
    case Rename = 'rename';
    case ValidateMetadata = 'validate_metadata';
}
