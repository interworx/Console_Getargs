<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 2004 The PHP Group                                     |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Bertrand Mansion <bmansion@mamasam.com>                      |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'PEAR.php';

/**#@+
 * Error Constants
 */
/**
 * Wrong configuration
 *
 * This error will be TRIGGERed when a configuration error is found, 
 * it will also issue a WARNING.
 */
define('CONSOLE_GETARGS_ERROR_CONFIG', -1);

/**
 * User made an error
 *
 * This error will be RETURNed when a bad parameter 
 * is found in the command line, for example an unknown parameter
 * or a parameter with an invalid number of options.
 */
define('CONSOLE_GETARGS_ERROR_USER', -2);

/**
 * Help text wanted
 *
 * This error will be RETURNed when the user asked to 
 * see the help by using <kbd>-h</kbd> or <kbd>--help</kbd> in the command line, you can then print
 * the help ascii art text by using the {@link Console_Getargs::getHelp()} method
 */
define('CONSOLE_GETARGS_HELP', -3);
/**#@-*/

/**
 * Command-line arguments parsing class
 * 
 * This implementation was freely inspired by a python module called
 * getargs by Vinod Vijayarajan and a perl CPAN module called
 * Getopt::Simple by Ron Savage
 *
 * This class implements a Command Line Parser that your cli applications
 * can use to parse command line arguments found in $_SERVER['argv']. 
 * It gives more flexibility and error checking than Console_Getopt. It also
 * performs some arguments validation and is capable to return a formatted
 * help text to the user, based on the configuration it is given.
 * 
 * The class provides the following capabilities:
 * - Each command line option can take an arbitrary number of arguments.
 * - Makes the distinction between switches (options without arguments) 
 *   and options that require arguments.
 * - Recognizes 'single-argument-options' and 'default-if-set' options.
 * - Switches and options with arguments can be interleaved in the command
 *   line.
 * - You can specify the maximum and minimum number of arguments an option
 *   can take. Use -1 if you don't want to specify an upper bound.
 * - Specify the default arguments to an option
 * - Short options can be more than one letter in length.
 * - A given option may be invoked by multiple names (aliases).
 * - Understands by default the --help, -h options
 * - Can return a formatted help text
 * - Arguments may be specified using the '=' syntax also.
 * 
 * @todo Implement the parsing of comma delimited arguments
 * @author Bertrand Mansion <bmansion@mamasam.com>
 * @copyright 2004
 * @license http://www.php.net/license/3_0.txt PHP License 3.0
 * @version @VER@
 * @package  Console_Getargs
 */
class Console_Getargs
{
    /**
     * Factory creates a new {@link Console_Getargs_Options} object
     *
     * This method will return a new {@link Console_Getargs_Options}
     * built using the given configuration options. If the configuration
     * or the command line options contain errors, the returned object will 
     * in fact be a PEAR_Error explaining the cause of the error.
     *
     * Factory expects an array as parameter.
     * The format for this array is:
     * <pre>
     * array(
     *  longname => array('short'   => Short option name,
     *                    'max'     => Maximum arguments for option,
     *                    'min'     => Minimum arguments for option,
     *                    'default' => Default option argument,
     *                    'desc'    => Option description)
     * )
     * </pre>
     * 
     * If an option can be invoked by more than one name, they have to be defined
     * by using | as a separator. For example: name1|name2
     * This works both in long and short names.
     *
     * max/min are the most/least number of arguments an option accepts.
     *
     * The 'defaults' field is optional and is used to specify default
     * arguments to an option. These will be assigned to the option if 
     * it is *not* used in the command line.
     * Default arguments can be:
     * - a single value for options that require a single argument,
     * - an array of values for options with more than one possible arguments.
     * Default argument(s) are mandatory for 'default-if-set' options.
     *
     * If max is 0 (option is just a switch), min is ignored.
     * If max is -1, then the option can have an unlimited number of arguments 
     * greater or equal to min.
     * 
     * If max == min == 1, the option is treated as a single argument option.
     * 
     * If max >= 1 and min == 0, the option is treated as a
     * 'default-if-set' option. This implies that it will get the default argument
     * only if the option is used in the command line without any value.
     * (Note: defaults *must* be specified for 'default-if-set' options) 
     *
     * If the option is not in the command line, the defaults are 
     * *not* applied. If an argument for the option is specified on the command
     * line, then the given argument is assigned to the option.
     * Thus:
     * - a --debug in the command line would cause debug = 'default argument'
     * - a --debug 2 in the command line would result in debug = 2
     *  if not used in the command line, debug will not be defined.
     * 
     * Example 1.
     * <code>
     * require_once 'Console_Getargs.php';
     *
     * $args =& Console_Getargs::factory($config);
     * 
     * if (PEAR::isError($args)) {
     *  if ($args->getCode() === CONSOLE_GETARGS_ERROR_USER) {
     *    echo Console_Getargs::getHelp($config, null, $args->getMessage())."\n";
     *  } else if ($args->getCode() === CONSOLE_GETARGS_HELP) {
     *    echo Console_Getargs::getHelp($config)."\n";
     *  }
     *  exit;
     * }
     * 
     * echo 'Verbose: '.$args->getValue('verbose')."\n";
     * if ($args->isDefined('bs')) {
     *  echo 'Block-size: '.(is_array($args->getValue('bs')) ? implode(', ', $args->getValue('bs'))."\n" : $args->getValue('bs')."\n");
     * } else {
     *  echo "Block-size: undefined\n";
     * }
     * echo 'Files: '.($args->isDefined('file') ? implode(', ', $args->getValue('file'))."\n" : "undefined\n");
     * if ($args->isDefined('n')) {
     *  echo 'Nodes: '.(is_array($args->getValue('n')) ? implode(', ', $args->getValue('n'))."\n" : $args->getValue('n')."\n");
     * } else {
     *  echo "Nodes: undefined\n";
     * }
     * echo 'Log: '.$args->getValue('log')."\n";
     * echo 'Debug: '.($args->isDefined('d') ? "YES\n" : "NO\n");
     * 
     * </code>
     * 
     * @param array associative array with keys being the options long name
     * @access public
     * @return object|PEAR_Error  a newly created Console_Getargs_Options object
     *                            or a PEAR_Error object on error
     */
    function &factory($config = array())
    {
        $obj =& new Console_Getargs_Options();

        $err = $obj->init($config);
        if ($err !== true) {
            return $err;
        }

        $err = $obj->buildMaps();
        if ($err !== true) {
            return $err;
        }

        $err = $obj->parseArgs();
        if ($err !== true) {
            return $err;
        }

        $err = $obj->setDefaults();
        if ($err !== true) {
            return $err;
        }

        return $obj;
    }

    /**
     * Returns an ascii art version of the help
     *
     * This method uses the given configuration and parameters
     * to create and format an help text for the options you defined
     * in your config parameter. You can supply a header and a footer
     * as well as the maximum length of a line. If you supplied
     * descriptions for your options, they will be used as well.
     *
     * By default, it returns something like this:
     * <pre>
     * Usage: myscript.php [options]
     * 
     * -f --files values(2)          Set the source and destination image files.
     * -w --width=&lt;value&gt;            Set the new width of the image.
     * -d --debug                    Switch to debug mode.
     * --formats values(1-3)         Set the image destination format. (jpegbig,
     *                               jpegsmall)
     * -fi --filters values(1-...)   Set the filters to be applied to the image upon
     *                               conversion. The filters will be used in the order
     *                               they are set.
     * -v --verbose (optional)value  Set the verbose level. (3)
     * </pre>
     *
     * @access public
     * @param  array  your args configuration
     * @param  string the header for the help. If it is left null,
     *                a default header will be used, starting by Usage:
     * @param  string the footer for the help. This could be used
     *                to supply a description of the error the user made
     * @param  int    help lines max length
     * @return string the formatted help text
     */
    function getHelp($config, $helpHeader = null, $helpFooter = '', $maxlength = 78)
    {
        $help = '';
        if (!isset($helpHeader)) {
            $help .= 'Usage: '.basename($_SERVER['SCRIPT_NAME'])." [options]\n\n";
        }
        $i = 0;
        foreach ($config as $long => $def) {
            
            $shortArr = array();
            if (isset($def['short'])) {
                $shortArr = explode('|', $def['short']);
            }
            $longArr = explode('|', $long);
            $col1[$i] = !empty($shortArr) ? '-'.$shortArr[0].' ' : '';
            $col1[$i] .= '--'.$longArr[0];
            $max = $def['max'];
            $min = isset($def['min']) ? $def['min'] : $max;

            if ($max === 1 && $min === 1) {
                $col1[$i] .= '=<value>';
            } else if ($max > 1) {
                if ($min === $max) {
                    $col1[$i] .= ' values('.$max.')';
                } else if ($min === 0) {
                    $col1[$i] .= ' values(optional)';
                } else {
                    $col1[$i] .= ' values('.$min.'-'.$max.')';
                }
            } else if ($max === 1 && $min === 0) {
                $col1[$i] .= ' (optional)value';
            } else if ($max === -1) {
                if ($min > 0) {
                    $col1[$i] .= ' values('.$min.'-...)';
                } else {
                    $col1[$i] .= ' (optional)values';
                }
            }

            if (isset($def['desc'])) {
                $col2[$i] = $def['desc'];
            } else {
                $col2[$i] = '';
            }
            if (isset($def['default'])) {
                if (is_array($def['default'])) {
                    $col2[$i] .= ' ('.implode(', ', $def['default']).')';
                } else {
                    $col2[$i] .= ' ('.$def['default'].')';
                }
            }
            $i++;
        }
        $arglen = 0;
        foreach ($col1 as $txt) {
            $length = strlen($txt);
            if ($length > $arglen) {
                $arglen = $length;
            }
        }
        $desclen = $maxlength - $arglen;
        $padding = str_repeat(' ', $arglen);
        foreach ($col1 as $k => $txt) {
            if (strlen($col2[$k]) > $desclen) {
                $desc = wordwrap($col2[$k], $desclen, "\n  ".$padding);
            } else {
                $desc = $col2[$k];
            }
            $help .= str_pad($txt, $arglen).'  '.$desc."\n";
        }
        return $help.$helpFooter;
    }
} // end class Console_Getargs

/**
 * This class implements a wrapper to the command line options and arguments.
 *
 * @author Bertrand Mansion <bmansion@mamasam.com>
 * @package  Console_Getargs
 */
class Console_Getargs_Options
{

    /**
     * Lookup to match short options name with long ones
     * @var array
     * @access private
     */
    var $_shortLong = array();

    /**
     * Lookup to match alias options name with long ones
     * @var array
     * @access private
     */
    var $_aliasLong = array();

    /**
     * Arguments set for the options
     * @var array
     * @access private
     */
    var $_longLong = array();

    /**
     * Configuration set at initialization time
     * @var array
     * @access private
     */
    var $_config = array();

    /**
     * A read/write copy of argv
     * @var array
     * @access private
     */
    var $args = array();

    /**
     * Initializes the Console_Getargs_Options object
     * @param array configuration options
     * @access private
     * @throws CONSOLE_GETARGS_ERROR_CONFIG
     * @return true|PEAR_Error
     */
    function init($config)
    {
        if (!isset($_SERVER['argv']) || !is_array($_SERVER['argv'])) {
            return PEAR::raiseError("Could not read argv", CONSOLE_GETARGS_ERROR_CONFIG,
                                    PEAR_ERROR_TRIGGER, E_USER_WARNING, 'Console_Getargs_Options::init()');
        }
        $this->args = $_SERVER['argv'];

        if (isset($this->args[0]{0}) && $this->args[0]{0} != '-') {
            array_shift($this->args);
        }
        $this->_config = $config;
        return true;
    }

    /**
     * Makes the lookup arrays for alias and short name mapping with long names
     * @access private
     * @throws CONSOLE_GETARGS_ERROR_CONFIG
     * @return true|PEAR_Error
     */
    function buildMaps()
    {
        foreach($this->_config as $long => $def) {

            $longArr = explode('|', $long);
            $longname = $longArr[0];

            if (count($longArr) > 1) {
                array_shift($longArr);
                foreach($longArr as $alias) {
                    if (isset($this->_aliasLong[$alias])) {
                        return PEAR::raiseError('Duplicate alias for long option '.$alias, CONSOLE_GETARGS_ERROR_CONFIG,
                                    PEAR_ERROR_TRIGGER, E_USER_WARNING, 'Console_Getargs_Options::buildMaps()');

                    }
                    $this->_aliasLong[$alias] = $longname;
                }
                $this->_config[$longname] = $def;
                unset($this->_config[$long]);
            }

            if (!empty($def['short'])) {
                // Short names
                $shortArr = explode('|', $def['short']);
                $short = $shortArr[0];
                if (count($shortArr) > 1) {
                    array_shift($shortArr);
                    foreach ($shortArr as $alias) {
                        if (isset($this->_shortLong[$alias])) {
                            return PEAR::raiseError('Duplicate alias for short option '.$alias, CONSOLE_GETARGS_ERROR_CONFIG,
                                    PEAR_ERROR_TRIGGER, E_USER_WARNING, 'Console_Getargs_Options::buildMaps()');
                        }
                        $this->_shortLong[$alias] = $longname;
                    }
                }
                $this->_shortLong[$short] = $longname;
            }
        }
        return true;
    }

    /**
     * Parses the given options/arguments one by one
     * @access private
     * @throws CONSOLE_GETARGS_HELP
     * @throws CONSOLE_GETARGS_ERROR_USER
     * @return true|PEAR_Error
     */
    function parseArgs()
    {
        for ($i = 0, $count = count($this->args); $i < $count; $i++) {

            $arg = $this->args[$i];

            if ($arg === '--') {
                // '--' alone breaks the loop
                break;
            }
            if ($arg === '--help' || $arg === '-h') {
                return PEAR::raiseError(null, CONSOLE_GETARGS_HELP, PEAR_ERROR_RETURN);
            }
            if (strlen($arg) > 1 && $arg{1} == '-') {
                $err = $this->parseArg(substr($arg, 2), true, $i);
            } else if (strlen($arg) > 1 && $arg{0} == '-') {
                $err = $this->parseArg(substr($arg, 1), false, $i);
            } else {
                $err = PEAR::raiseError('Unknown argument '.$arg,
                                     CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                                     null, 'Console_Getargs_Options::parseArgs()');
            }
            if ($err !== true) {
                return $err;
            }
        }
        return true;
    }

    /**
     * Parses one option/argument
     * @access private
     * @throws CONSOLE_GETARGS_ERROR_USER
     * @return true|PEAR_Error
     */
    function parseArg($arg, $isLong, &$pos)
    {
        $opt = '';
        for ($i = 0; $i < strlen($arg); $i++) {
            $opt .= $arg{$i};
            if ($isLong === false && isset($this->_shortLong[$opt])) {
                $cmp = $opt;
                $long = $this->_shortLong[$opt];
            } else if ($isLong === true && isset($this->_config[$opt])) {
                $long = $cmp = $opt;
            }
            if ($arg{$i} === '=') {
                break;
            }
        }

        if (isset($long)) {
            if (strlen($arg) > strlen($cmp)) {
                $arg = substr($arg, strlen($cmp));
                if ($arg{0} === '=') {
                    $arg = substr($arg, 1);
                }
            } else {
                $arg = '';
            }
            return $this->setValue($long, $arg, $pos);
        }
        return PEAR::raiseError('Unknown argument '.$opt,
                                CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                                null, 'Console_Getargs_Options::parseArg()');
    }

    /**
     * Set the option arguments
     * @access private
     * @throws CONSOLE_GETARGS_ERROR_CONFIG
     * @throws CONSOLE_GETARGS_ERROR_USER
     * @return true|PEAR_Error
     */
    function setValue($optname, $value, &$pos)
    {
        if (!isset($this->_config[$optname]['max'])) {
            return PEAR::raiseError('No max parameter set for '.$optname,
                                     CONSOLE_GETARGS_ERROR_CONFIG, PEAR_ERROR_TRIGGER,
                                     E_USER_WARNING, 'Console_Getargs_Options::setValue()');
        }

        $max = $this->_config[$optname]['max'];
        $min = isset($this->_config[$optname]['min']) ? $this->_config[$optname]['min']: $max;

        if ($value !== '') {
            // Argument is like -v5
            if ($min == 1 && $max > 0) {
                $this->updateValue($optname, $value);
                return true;
            }
            if ($max === 0) {
                return PEAR::raiseError('Argument '.$optname.' does not take any value',
                                     CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                                     null, 'Console_Getargs_Options::setValue()');
            }
            return PEAR::raiseError('Argument '.$optname.' expects more than one value',
                                     CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                                     null, 'Console_Getargs_Options::setValue()');
        }

        if ($min === 1 && $max === 1) {
            // Argument requires 1 value
            if (isset($this->args[$pos+1]) && $this->isValue($this->args[$pos+1])) {
                $this->updateValue($optname, $this->args[$pos+1]);
                $pos++;
                return true;
            }
            return PEAR::raiseError('Argument '.$optname.' expects one value',
                             CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                             null, 'Console_Getargs_Options::setValue()');

        } else if ($max === 0) {
            // Argument is a switch
            if (isset($this->args[$pos+1]) && $this->isValue($this->args[$pos+1])) {
                return PEAR::raiseError('Argument '.$optname.' does not take any value',
                                 CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                                 null, 'Console_Getargs_Options::setValue()');
            }
            $this->updateValue($optname, true);
            return true;

        } else if ($max >= 1 && $min === 0) {
            // Argument has a default-if-set value
            if (!isset($this->_config[$optname]['default'])) {
                return PEAR::raiseError('No default value defined for '.$optname,
                                 CONSOLE_GETARGS_ERROR_CONFIG, PEAR_ERROR_TRIGGER,
                                 E_USER_WARNING, 'Console_Getargs_Options::setValue()');
            }
            if (is_array($this->_config[$optname]['default'])) {
                return PEAR::raiseError('Default value for '.$optname.' must be scalar',
                                 CONSOLE_GETARGS_ERROR_CONFIG, PEAR_ERROR_TRIGGER,
                                 E_USER_WARNING, 'Console_Getargs_Options::setValue()');
            }
            if (isset($this->args[$pos+1]) && $this->isValue($this->args[$pos+1])) {
                $this->updateValue($optname, $this->args[$pos+1]);
                $pos++;
                return true;
            }
            $this->updateValue($optname, $this->_config[$optname]['default']);
            return true;
        }

        // Argument takes one or more values
        $added = 0;
        for ($i = $pos + 1; $i <= count($this->args); $i++) {
            if (isset($this->args[$i]) && $this->isValue($this->args[$i])) {
                $this->updateValue($optname, $this->args[$i]);
                $added++;
                $pos++;
                continue;
            }
            if ($min > $added) {
                return PEAR::raiseError('Argument '.$optname.' expects at least '.$min.(($min > 1) ? ' values' : ' value'),
                         CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                         null, 'Console_Getargs_Options::setValue()');
            } else if ($max !== -1 && $added > $max) {
                return PEAR::raiseError('Argument '.$optname.' expects maximum '.$max.' values',
                         CONSOLE_GETARGS_ERROR_USER, PEAR_ERROR_RETURN,
                         null, 'Console_Getargs_Options::setValue()');
            }
            break;
        }
        return true;
    }

    /**
     * Checks whether the given parameter is an argument or an option
     * @access private
     * @return boolean
     */
    function isValue($arg)
    {
        if (strlen($arg) > 1 && $arg{1} == '-' ||
            strlen($arg) > 1 && $arg{0} == '-') {
            return false;
        }
        return true;
    }

    /**
     * Adds the argument to the option
     *
     * If the argument for the option is already set,
     * the option arguments will be changed to an array
     * @access private
     * @return void
     */
    function updateValue($optname, $value)
    {
        if (isset($this->_longLong[$optname])) {
            if (is_array($this->_longLong[$optname])) {
                $this->_longLong[$optname][] = $value;
            } else {
                $prevValue = $this->_longLong[$optname];
                $this->_longLong[$optname] = array($prevValue);
                $this->_longLong[$optname][] = $value;
            }
        } else {
            $this->_longLong[$optname] = $value;
        }
    }

    /**
     * Sets the option default arguments when necessary
     * @access private
     * @return true
     */
    function setDefaults()
    {
        foreach ($this->_config as $longname => $def) {
            if (isset($def['default']) && $def['min'] !== 0 && !isset($this->_longLong[$longname])) {
                $this->_longLong[$longname] = $def['default'];
            }
        }
        return true;
    }

    /**
     * Checks whether the given option is defined
     *
     * An option will be defined if an argument was assigned to it using
     * the command line options. You can use the short, the long or
     * an alias name as parameter.
     *
     * @access public
     * @param  string the name of the option to be checked
     * @return boolean true if the option is defined
     */
    function isDefined($optname)
    {
        $longname = $this->getLongName($optname);
        if (isset($this->_longLong[$longname])) {
            return true;
        }
        return false;
    }

    /**
     * Returns the long version of the given parameter
     *
     * If the given name is not found, it will return the name that
     * was given, without further ensuring that the option
     * actually exists
     *
     * @access private
     * @param  string the name of the option
     * @return string long version of the option name
     */
    function getLongName($optname)
    {
        if (isset($this->_shortLong[$optname])) {
            $longname = $this->_shortLong[$optname];
        } else if (isset($this->_aliasLong[$optname])) {
            $longname = $this->_aliasLong[$optname];
        } else {
            $longname = $optname;
        }
        return $longname;
    }

    /**
     * Returns the argument of the given option
     *
     * You can use the short, alias or long version of the option name.
     * This method will try to find the argument(s) of the given option name.
     * If it is not found it will return null. If the arg has more than
     * one argument, an array of arguments will be returned.
     *
     * @access public
     * @param  string the name of the option
     * @return array|string|null argument(s) associated with the option
     */
    function getValue($optname)
    {
        if ($this->isDefined($optname)) {
            $longname = $this->getLongName($optname);
            return $this->_longLong[$longname];
        }
        return null;
    }
} // end class Console_Getargs_Options