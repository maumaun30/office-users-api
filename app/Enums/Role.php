<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    /** Non-human account used by other services (e.g. the inventory API) to read users. */
    case Service = 'service';
}
