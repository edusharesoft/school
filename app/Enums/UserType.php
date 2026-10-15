<?php

namespace App\Enums;

enum UserType: string
{
    case Platform = 'platform';
    case Staff = 'staff';
    case Teacher = 'teacher';
    case Student = 'student';
    case Parent = 'parent';

    public function label(): string
    {
        return __(match ($this) {
            self::Platform => 'Platform',
            self::Staff => 'Staff',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
            self::Parent => 'Parent/Guardian',
        });
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
