<?php

namespace App\Enums;

enum FileVisibility: string
{
    case PRIVATE = 'private';
    case SHARED = 'shared';
    case PUBLIC = 'public';
}
