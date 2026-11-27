<?php

namespace App\Models\Enums;

enum PushDeliveryStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Expired = 'expired';
    case Skipped = 'skipped';
}
