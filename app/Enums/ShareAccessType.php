<?php

namespace App\Enums;

enum ShareAccessType: string
{
    case PUBLIC_LINK = 'public_link';
    case SPECIFIC_USER = 'specific_user';
    case SPECIFIC_WHATSAPP = 'specific_whatsapp';
}
