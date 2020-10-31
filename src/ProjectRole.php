<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

final class ProjectRole
{
    public const ADMIN = 'admin';
    public const GUEST = 'guest';
    public const READ_ONLY = 'readOnly';
    public const SHARE = 'share';
}
