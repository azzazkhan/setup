<?php

declare(strict_types=1);

namespace App\Enums;

enum Pagination: string
{
    case LengthAware = 'length_aware';
    case Simple = 'simple';
    case Cursor = 'cursor';
}
