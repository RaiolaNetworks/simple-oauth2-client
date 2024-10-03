<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Enums;

enum LoginModesEnum: string
{
    case BOTH     = 'BOTH';
    case OAUTH    = 'OAUTH';
    case PASSWORD = 'PASSWORD';
}
