<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit38658c24433224ba35a28b7f458965b7
{
    public static $files = array (
        '6e3fae29631ef280660b3cdad06f25a8' => __DIR__ . '/..' . '/symfony/deprecation-contracts/function.php',
        'a4a119a56e50fbb293281d9a48007e0e' => __DIR__ . '/..' . '/symfony/polyfill-php80/bootstrap.php',
        'ef6802c8a38664a4b1e8712ed25377fb' => __DIR__ . '/..' . '/shuber/curl/curl.php',
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        'def43f6c87e4f8dfd0c9e1b1bab14fe8' => __DIR__ . '/..' . '/symfony/polyfill-iconv/bootstrap.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WhatArmy\\Watchtower\\Mysql\\' => 26,
            'WhatArmy\\Watchtower\\Files\\' => 26,
            'WhatArmy\\Watchtower\\' => 20,
        ),
        'S' => 
        array (
            'Symfony\\Polyfill\\Php80\\' => 23,
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Polyfill\\Iconv\\' => 23,
            'Symfony\\Polyfill\\Ctype\\' => 23,
            'Symfony\\Component\\Finder\\' => 25,
        ),
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
            'PhpZip\\' => 7,
        ),
        'D' => 
        array (
            'Druidfi\\Mysqldump\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WhatArmy\\Watchtower\\Mysql\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/Mysql',
        ),
        'WhatArmy\\Watchtower\\Files\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/Files',
        ),
        'WhatArmy\\Watchtower\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Symfony\\Polyfill\\Php80\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-php80',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Polyfill\\Iconv\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-iconv',
        ),
        'Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'Symfony\\Component\\Finder\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/finder',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'PhpZip\\' => 
        array (
            0 => __DIR__ . '/..' . '/nelexa/zip/src',
        ),
        'Druidfi\\Mysqldump\\' => 
        array (
            0 => __DIR__ . '/..' . '/druidfi/mysqldump-php/src',
        ),
    );

    public static $classMap = array (
        'Attribute' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/Attribute.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'PhpToken' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/PhpToken.php',
        'Stringable' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/Stringable.php',
        'UnhandledMatchError' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/UnhandledMatchError.php',
        'ValueError' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/ValueError.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit38658c24433224ba35a28b7f458965b7::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit38658c24433224ba35a28b7f458965b7::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit38658c24433224ba35a28b7f458965b7::$classMap;

        }, null, ClassLoader::class);
    }
}
