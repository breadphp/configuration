<?php
/**
 * Bread PHP Framework (http://github.com/saiv/Bread)
 * Copyright 2010-2012, SAIV Development Team <development@saiv.it>
 *
 * Licensed under a Creative Commons Attribution 3.0 Unported License.
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright  Copyright 2010-2012, SAIV Development Team <development@saiv.it>
 * @link       http://github.com/saiv/Bread Bread PHP Framework
 * @package    Bread
 * @since      Bread PHP Framework
 * @license    http://creativecommons.org/licenses/by/3.0/
 */
namespace Bread\Configuration;

use Bread\Caching\Cache;
use Bread\Promises\When;
use Exception;

class Manager
{

    private static $defaults = array();

    private static $configurations = array();

    public static function initialize($url, $cache = false)
    {
        switch ($scheme = parse_url($url, PHP_URL_SCHEME)) {
            case 'file':
                $directory = parse_url($url, PHP_URL_PATH);
                if ($cache) {
                    return Cache::instance()->fetch(__METHOD__)->then(null, function ($key) use ($directory) {
                        return static::parse($directory);
                    })->then(function ($configurations) {
                        return Cache::instance()->store(__METHOD__, $configurations);
                    });
                } else {
                    return When::resolve(static::parse($directory));
                }
            case 'mysql':
            default:
                throw new Exception("Configuration scheme '$scheme' currently not supported");
        }
    }

    public static function defaults($class, $configuration = array())
    {
        if ($parent = get_parent_class($class)) {
            $configuration = array_replace_recursive(static::get($parent), $configuration);
        }
        foreach (class_uses($class) as $trait) {
            $configuration = array_replace_recursive(static::get($trait), $configuration);
        }
        if (isset(static::$configurations[$class])) {
            $configuration = array_replace_recursive($configuration, static::$configurations[$class]);
        }
        static::$configurations[$class] = $configuration;
    }

    public static function configure($class, $configuration = array())
    {
        if (!isset(static::$configurations[$class])) {
            static::$configurations[$class] = array();
        }
        static::$configurations[$class] = array_replace_recursive(static::$configurations[$class], $configuration);
    }

    public static function get($class, $key = null)
    {
        static::defaults($class);
        if (!isset(static::$configurations[$class])) {
            return null;
        }
        $configuration = static::$configurations[$class];
        if (null === $key) {
            return $configuration;
        }
        foreach (explode('.', $key) as $key) {
            if (!isset($configuration[$key])) {
                return null;
            }
            $configuration = $configuration[$key];
        }
        return $configuration;
    }

    public static function set($class, $key, $value = null)
    {
        if (!isset(static::$configurations[$class])) {
            static::$configurations[$class] = array();
        }
        $configuration = static::$configurations[$class];
        if (is_array($key)) {
            $newConfiguration = $key;
        } else {
            $newConfiguration = Parsers\Initialization::parse(array(
                $key => $value
            ), false);
        }
        static::$configurations[$class] = array_replace_recursive($configuration, $newConfiguration);
    }
    
    protected static function parse($directory)
    {
        $configurations = array();
        if (!is_dir($directory)) {
            throw new Exception("Configuration directory $directory is not valid.");
        }
        foreach ((array) scandir($directory) as $path) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $path = $directory . DIRECTORY_SEPARATOR . $path;
            if ($Parser = static::get(__CLASS__, "parsers.$extension")) {
                $configurations = array_replace_recursive($configurations, $Parser::parse($path));
            }
        }
        return static::$configurations = array_merge($configurations, static::$configurations);
    }
}

Manager::defaults('Bread\Configuration\Manager', array(
    'parsers' => array(
        'ini' => 'Bread\Configuration\Parsers\Initialization',
        'php' => 'Bread\Configuration\Parsers\PHP'
    )
));
