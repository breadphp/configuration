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
namespace Bread\Configuration\Parsers;

use Bread\Configuration\Interfaces\Parser;
use Exception;

/**
 * Allows for multi-dimensional ini files.
 *
 * The native parse_ini_file() function will convert the following ini file:
 *
 * [production]
 * localhost.database.host = 1.2.3.4
 * localhost.database.user = root
 * localhost.database.password = abcdef
 * debug.enabled = false
 *
 * [development : production]
 * localhost.database.host = localhost
 * debug.enabled = true
 *
 * into the following array:
 *
 * Array (
 * 'localhost.database.host' => 'localhost',
 * 'localhost.database.user' => 'root',
 * 'localhost.database.password' => 'abcdef',
 * 'debug.enabled' => 1
 * )
 *
 * This class allows you to convert the specified ini file into a multi-dimensional
 * array. In this case the structure generated will be:
 *
 * Array (
 * 'localhost' => Array (
 * 'database' => Array (
 * 'host' => 'localhost',
 * 'user' => 'root',
 * 'password' => 'abcdef',
 * ),
 * 'debug' => Array (
 * 'enabled' => 1
 * )
 * )
 * )
 *
 * As you can also see you can have sections that extend other sections (use ":" for that).
 * The extendable section must be defined BEFORE the extending section or otherwise
 * you will get an exception.
 */
class Initialization implements Parser
{

    /**
     * Internal storage array
     *
     * @var array
     */
    private static $result = array();

    /**
     * Loads in the ini file specified in filename, and returns the settings in
     * it as an associative multi-dimensional array
     *
     * @param string $filename
     *            The filename of the ini file being parsed
     * @param boolean $process_sections
     *            By setting the process_sections parameter to TRUE,
     *            you get a multidimensional array, with the section
     *            names and settings included. The default for
     *            process_sections is FALSE
     * @param string $section_name
     *            Specific section name to extract upon processing
     * @throws Exception
     * @return array boolean
     */
    public static function parse($filename, $process_sections = true, $section_name = null)
    {
        if (is_array($filename)) {
            $ini = $filename;
        } elseif (file_exists($filename)) {
            // load the raw ini file
            $ini = parse_ini_file($filename, $process_sections);
        } else {
            $ini = parse_ini_string($filename, $process_sections);
        }
        
        if ($ini === false) {
            return array();
        }
        
        // reset the result array
        self::$result = array();
        
        if ($process_sections === true) {
            // loop through each section
            foreach ($ini as $section => $contents) {
                // process sections contents
                self::processSection($section, $contents);
            }
        } else {
            // treat the whole ini file as a single section
            self::$result = self::processSectionContents($ini);
        }
        
        // extract the required section if required
        if ($process_sections === true) {
            if ($section_name !== null) {
                // return the specified section contents if it exists
                if (isset(self::$result[$section_name])) {
                    return self::$result[$section_name];
                } else {
                    throw new Exception('Section ' . $section_name . ' not found in the initilanization file');
                }
            }
        }
        
        // if no specific section is required, just return the whole result
        return self::$result;
    }

    /**
     * Process contents of the specified section
     *
     * @param string $section
     *            Section name
     * @param array $contents
     *            Section contents
     * @throws Exception
     * @return void
     */
    private static function processSection($section, array $contents)
    {
        if (stripos($section, ':') === false) {
            self::$result[$section] = self::processSectionContents($contents);
        } else {
            // extract section names
            list ($ext_target, $ext_source) = explode(':', $section);
            $ext_target = trim($ext_target);
            $ext_source = trim($ext_source);
            
            // check if the extended section exists
            if (!isset(self::$result[$ext_source])) {
                throw new Exception('Unable to extend section ' . $ext_source . ', section not found');
            }
            
            // process section contents
            self::$result[$ext_target] = self::processSectionContents($contents);
            
            // merge the new section with the existing section values
            self::$result[$ext_target] = self::arrayMergeRecursive(self::$result[$ext_source], self::$result[$ext_target]);
        }
    }

    /**
     * Process contents of a section
     *
     * @param array $contents
     *            Section contents
     * @return array
     */
    private static function processSectionContents(array $contents)
    {
        $result = array();
        
        // loop through each line and convert it to an array
        foreach ($contents as $path => $value) {
            // convert all a.b.c.d to multi-dimensional arrays
            $process = self::processContentEntry($path, $value);
            
            // merge the current line with all previous ones
            $result = self::arrayMergeRecursive($result, $process);
        }
        
        return $result;
    }

    /**
     * Convert a.b.c.d paths to multi-dimensional arrays
     *
     * @param string $path
     *            Current ini file's line's key
     * @param mixed $value
     *            Current ini file's line's value
     * @return array
     */
    private static function processContentEntry($path, $value)
    {
        $pos = strpos($path, '.');
        
        if ($pos === false) {
            return array(
                $path => $value
            );
        }
        
        $key = substr($path, 0, $pos);
        $path = substr($path, $pos + 1);
        
        $result = array(
            $key => self::processContentEntry($path, $value)
        );
        
        return $result;
    }

    /**
     * Merge two arrays recursively overwriting the keys in the first array
     * if such key already exists
     *
     * @param mixed $a
     *            Left array to merge right array into
     * @param mixed $b
     *            Right array to merge over the left array
     * @return mixed
     */
    private static function arrayMergeRecursive($a, $b)
    {
        // merge arrays if both variables are arrays
        if (is_array($a) && is_array($b)) {
            // loop through each right array's entry and merge it into $a
            foreach ($b as $key => $value) {
                if (isset($a[$key])) {
                    $a[$key] = self::arrayMergeRecursive($a[$key], $value);
                } else {
                    if ($key === 0) {
                        $a = array(
                            0 => self::arrayMergeRecursive($a, $value)
                        );
                    } else {
                        $a[$key] = $value;
                    }
                }
            }
        } else {
            // one of values is not an array
            $a = $b;
        }
        return $a;
    }
}
