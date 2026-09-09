<?php

namespace App\Enums;

enum SharePermissionType: string
{
    case VIEWER = 'viewer';
    case COMMENTER = 'commenter';
    case EDITOR = 'editor';
}
