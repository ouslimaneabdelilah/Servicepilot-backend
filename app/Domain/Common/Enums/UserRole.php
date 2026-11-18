<?php

namespace App\Domain\Common\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case DISPATCHER = 'dispatcher';
    case TECHNICIAN = 'technician';
    case ACCOUNTANT = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Company Owner',
            self::ADMIN => 'Administrator',
            self::DISPATCHER => 'Dispatcher',
            self::TECHNICIAN => 'Field Technician',
            self::ACCOUNTANT => 'Accountant',
        };
    }
}

