<?php

declare(strict_types=1);

namespace Conductor;

/** The application contributes no services of its own; the entry exists for laminas-component-installer. */
final class ConfigProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
