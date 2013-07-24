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
namespace Bread\Configuration\Interfaces;

/**
 * The interface for configuration parsers.
 *
 * @author Giovanni Lovato <heruan@aldu.net>
 */
interface Parser
{

    /**
     * Parses a configuration file and returns an array with configuration entries.
     *
     * @param string $filename
     *            The file to parse
     * @return array The configuration array
     */
    public static function parse($filename);
}
