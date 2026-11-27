<?php

namespace OpenVRE;


enum Site: string
{
    case Local = "local";
    case SSH = "SSH";
    case Swift = "Swift";
    case EGA = "EGA";
    case MareNostrum = "MareNostrum";

    /**
     * Whether files from this site are registered in the workspace without
     * being stored on local disk (they are only staged at job execution time).
     * Only local site files live on local disk.
     */
    public function isRemote(): bool
    {
        return $this !== self::Local;
    }

    public static function isRemoteSource(?string $value): bool
    {
        return $value !== null && (self::tryFrom($value)?->isRemote() ?? false);
    }
}
