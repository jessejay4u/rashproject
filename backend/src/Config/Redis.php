<?php

declare(strict_types=1);

namespace App\Config;

class Redis
{
    private static ?Cache $instance = null;

    public static function connection(): Cache
    {
        if (self::$instance === null) {
            self::$instance = new Cache();
        }
        return self::$instance;
    }
}
