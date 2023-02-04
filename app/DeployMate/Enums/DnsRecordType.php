<?php

namespace App\DeployMate\Enums;

enum DnsRecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case CNAME = 'CNAME';
    case MX = 'MX';
    case NS = 'NS';
    case SRV = 'SRV';
    case TXT = 'TXT';
}
