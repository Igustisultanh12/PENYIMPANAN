<?php

namespace App\Enums;

enum FileStatus: string
{
    case UPLOADING = 'uploading';
    case READY = 'ready';
    case QUARANTINED = 'quarantined';
    case ERROR = 'error';
}
