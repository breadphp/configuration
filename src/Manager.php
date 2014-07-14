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

    public static function defaults($class, $configuration = array(), $domain = '__default__')
    {
        if ($parent = get_parent_class($class)) {
            $configuration = array_replace_recursive(static::get($parent, null, $domain), $configuration);
        }
        foreach (class_uses($class) as $trait) {
            $configuration = array_replace_recursive(static::get($trait, null, $domain), $configuration);
        }
        if (isset(static::$configurations[$domain][$class])) {
            $configuration = array_replace_recursive($configuration, static::$configurations[$domain][$class]);
        }
        static::$configurations[$domain][$class] = $configuration;
    }

    public static function configure($class, $configuration = array(), $domain = '__default__')
    {
        if (!isset(static::$configurations[$domain][$class])) {
            static::$configurations[$domain][$class] = array();
        }
        static::$configurations[$domain][$class] = array_replace_recursive(static::$configurations[$domain][$class], $configuration);
    }

    public static function get($class, $keys = null, $domain = '__default__')
    {
        if (!isset(static::$configurations[$domain])) {
            static::$configurations[$domain] = array();
        }
        if (!isset(static::$configurations[$domain][$class])) {
            static::defaults($class, array(), $domain);
        }
        $configuration = static::$configurations[$domain][$class];
        if (null !== $keys) {
            foreach (explode('.', $keys) as $key) {
                if (!isset($configuration[$key])) {
                    if ($domain != '__default__') {
                        if (get_parent_class($class)) {
                            $configuration = static::get($class, $keys) ? : static::get(get_parent_class($class), $keys, $domain);
                        } else {
                            $configuration = static::get($class, $keys);
                        }
                    } else {
                        if (get_parent_class($class)) {
                            $configuration = static::get(get_parent_class($class), $keys, $domain);
                        } else {
                            return null;
                        }
                    }
                } else {
                    $configuration = $configuration[$key];
                }
            }
        }
        return $configuration;
    }

    public static function set($class, $key, $value = null, $domain = '__default__')
    {
        if (!isset(static::$configurations[$domain][$class])) {
            static::$configurations[$domain][$class] = array();
        }
        $configuration = static::$configurations[$domain][$class];
        if (is_array($key)) {
            $newConfiguration = $key;
        } else {
            $newConfiguration = Parsers\Initialization::parse(array(
                $key => $value
            ), false);
        }
        static::$configurations[$domain][$class] = array_replace_recursive($configuration, $newConfiguration);
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
        return static::$configurations = array_merge_recursive($configurations, static::$configurations);
    }
}

Manager::defaults('Bread\Configuration\Manager', array(
    'parsers' => array(
        'ini' => 'Bread\Configuration\Parsers\Initialization',
        'php' => 'Bread\Configuration\Parsers\PHP'
    )
));
