<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit91666ab20fa90d6d5077ec8b7a048825
{
    public static $files = array (
        'ef6802c8a38664a4b1e8712ed25377fb' => __DIR__ . '/..' . '/shuber/curl/curl.php',
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        'def43f6c87e4f8dfd0c9e1b1bab14fe8' => __DIR__ . '/..' . '/symfony/polyfill-iconv/bootstrap.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WhatArmy\\Watchtower\\' => 20,
        ),
        'S' => 
        array (
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
        'I' => 
        array (
            'Ifsnop\\' => 7,
        ),
        'C' => 
        array (
            'ClaudioSanches\\WPAutoloader\\' => 28,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WhatArmy\\Watchtower\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
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
        'Ifsnop\\' => 
        array (
            0 => __DIR__ . '/..' . '/ifsnop/mysqldump-php/src/Ifsnop',
        ),
        'ClaudioSanches\\WPAutoloader\\' => 
        array (
            0 => __DIR__ . '/..' . '/claudiosanches/wp-autoloader/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'MySQLDump' => __DIR__ . '/..' . '/dg/mysql-dump/src/MySQLDump.php',
        'MySQLImport' => __DIR__ . '/..' . '/dg/mysql-dump/src/MySQLImport.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit91666ab20fa90d6d5077ec8b7a048825::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit91666ab20fa90d6d5077ec8b7a048825::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit91666ab20fa90d6d5077ec8b7a048825::$classMap;

        }, null, ClassLoader::class);
    }
}
