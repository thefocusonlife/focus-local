<?php
namespace PhpBook\Validate; // Namespace declaration

class Validate
{
    public static function isbool($number, $min = 0, $max = 1): bool
    {
        return $number >= $min and $number <= 1;
    }

    public static function isNumber($number, $min = 0, $max = 100): bool
    {
        return $number >= $min and $number <= $max;
    }

    public static function isText(string $string, int $min = 0, int $max = 1000): bool
    {
        $length = mb_strlen($string);
        return $length >= $min and $length <= $max;
    }

    public static function isEmail($email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
    }

    public static function isPassword($password)
    {
        if (
            mb_strlen($password) >= 8 and // Length 8 or more chars
            preg_match('/[A-Z]/', $password) and // Contains uppercase A-Z
            preg_match('/[a-z]/', $password) and // Contains lowercase a-z
            preg_match('/[0-9]/', $password) // Contains 0-9
        ) {
            return true; // Passed all tests
        }
        return false; // Invalid password
    }

    public static function isMemberId($member_id, array $member_list): bool
    {
        foreach ($member_list as $member) {
            if ($member['id'] == $member_id) {
                return true;
            }
        }
        return false;
    }

    public static function isMenuId($menu_id, array $menu_list): bool
    {
        foreach ($menu_list as $menu) {
            if ($menu['id'] == $menu_id) {
                return true;
            }
        }
        return false;
    }
}
