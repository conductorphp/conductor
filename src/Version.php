<?php

declare(strict_types=1);

namespace Conductor;

use Composer\InstalledVersions;

final class Version
{
    public const PACKAGE = 'conductor/conductor';

    /** The installed release, or `dev` from a checkout that composer does not know as this package. */
    public static function current(): string
    {
        return InstalledVersions::isInstalled(self::PACKAGE)
            ? (string) InstalledVersions::getPrettyVersion(self::PACKAGE)
            : 'dev';
    }
}
