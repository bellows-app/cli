<?php

namespace Bellows\Enums;

enum JobFrequency: string
{
    case MINUTELY = 'minutely';
    case HOURLY = 'hourly';
    case NIGHTLY = 'nightly';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case REBOOT = 'reboot';
    case CUSTOM = 'custom';
}
