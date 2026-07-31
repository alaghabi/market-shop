<?php

namespace App\Enum;

enum AccountSubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Replaced = 'replaced';
}
