<?php

class Kint
{
    /**
     * @var mixed Kint mode
     *
     * false: Disabled
     * true: Enabled, automatic mode selection
     * Kint::MODE_*: Manual mode selection
     */
    public static $enabledMode = true;

    /**
     * @var bool Delay output until script shutdown
     */
    public static $delayedMode;

    /**
     * @var bool Return output instead of echoing
     */
    public static $returnOutput;

    /**
     * @var string format of the link to the source file in trace entries. Use %f for file path, %l for line number.
     *
     * [!] EXAMPLE (works with for phpStorm and RemoteCall Plugin):
     *
     * Kint::$fileLinkFormat = 'http://localhost:8091/?message=%f:%l';
     */
    public static $fileLinkFormat = '';

    /**
     * @var bool whether to display where kint was called from
     */
    public static $displayCalledFrom = true;

    /**
     * @var int max length of string before it is truncated and displayed separately in full.
     *          Zero or false to disable
     */
    public static $maxStrLength = 80;

    /**
     * @var array base directories of your application that will be displayed instead of the full path. Keys are paths,
     *            values are replacement strings
     *
     * [!] EXAMPLE (for Kohana framework):
     *
     * Kint::$appRootDirs = array(
     *      APPPATH => 'APPPATH',
     *      SYSPATH => 'SYSPATH',
     *      MODPATH => 'MODPATH',
     *      DOCROOT => 'DOCROOT',
     * );
     *
     * [!] EXAMPLE #2 (for a semi-universal approach)
     *
     * Kint::$appRootDirs = array(
     *      realpath( __DIR__ . '/../../..' ) => 'ROOT', // go up as many levels as needed in the realpath() param
     * );
     */
    public static $appRootDirs = array();

    /**
     * @var int max array/object levels to go deep, if zero no limits are applied
     */
    public static $maxLevels = 7;

    /**
     * @var bool expand all trees by default for rich view
     */
    public static $expandedByDefault = false;

    /**
     * @var bool enable detection when Kint is command line. Formats output with whitespace only; does not HTML-escape it
     */
    public static $cliDetection = true;

    /**
     * @var bool in addition to above setting, enable detection when Kint is run in *UNIX* command line.
     *           Attempts to add coloring, but if seen as plain text, the color information is visible as gibberish
     */
    public static $cliColors = true;

    /**
     * @var array Kint aliases. Add debug functions in Kint wrappers here to fix modifiers and backtraces
     */
    public static $aliases = array(
        array('Kint', 'dump'),
        array('Kint', 'trace'),
    );

    /**
     * @var array Kint_Renderer descendants. Add to array to extend.
     */
    public static $renderers = array(
        self::MODE_RICH => 'Kint_Renderer_Rich',
        self::MODE_JS => 'Kint_Renderer_Js',
        self::MODE_PLAIN => 'Kint_Renderer_Plain',
    );

    const MODE_RICH = 'r';
    const MODE_WHITESPACE = 'w';
    const MODE_CLI = 'c';
    const MODE_PLAIN = 'p';
    const MODE_JS = 'j';

    /**
     * Stashes or sets all settings at once.
     *
     * @param array|null $settings Array of all settings to be set or null to set none
     *
     * @return array Current settings
     */
    public static function settings(array $settings = null)
    {
        static $keys = array(
            'aliases',
            'appRootDirs',
            'cliColors',
            'cliDetection',
            'renderers',
            'delayedMode',
            'displayCalledFrom',
            'enabledMode',
            'expandedByDefault',
            'fileLinkFormat',
            'maxLevels',
            'maxStrLength',
            'returnOutput',
        );

        $out = array();

        foreach ($keys as $key) {
            $out[$key] = self::$$key;
        }

        if ($settings !== null) {
            $in = array_intersect_key($settings, array_flip($keys));
            foreach ($in as $key => $val) {
                self::$$key = $val;
            }
        }

        return $out;
    }

    /**
     * Prints a debug backtrace, same as Kint::dump(1).
     *
     * @param array $trace [OPTIONAL] you can pass your own trace, otherwise, `debug_backtrace` will be called
     *
     * @return mixed
     */
    public static function trace($trace = null)
    {
        if (!self::$enabledMode) {
            return '';
        }

        return self::dump(isset($trace) ? $trace : debug_backtrace(true));
    }

    /**
     * Dump information about variables, accepts any number of parameters, supports modifiers:.
     *
     *  clean up any output before kint and place the dump at the top of page:
     *   - Kint::dump()
     *  *****
     *  expand all nodes on display:
     *   ! Kint::dump()
     *  *****
     *  dump variables disregarding their depth:
     *   + Kint::dump()
     *  *****
     *  return output instead of displaying it:
     *
     *   @ Kint::dump()
     *  *****
     *  force output as plain text
     *   ~ Kint::dump()
     *
     * Modifiers are supported by all dump wrapper functions, including Kint::trace(). Space is optional.
     *
     * @param mixed $data
     *
     * @return void|string
     */
    public static function dump($data = null)
    {
        if (!self::$enabledMode) {
            return '';
        }

        $stash = self::settings();

        list($names, $modifiers, $callee, $caller, $miniTrace) = self::_getCalleeInfo(
            defined('DEBUG_BACKTRACE_IGNORE_ARGS')
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
                : debug_backtrace()
        );

        // set mode for current run
        if (self::$enabledMode === true) {
            self::$enabledMode = self::MODE_RICH;
            if (PHP_SAPI === 'cli' && self::$cliDetection === true) {
                self::$enabledMode = self::MODE_CLI;
            }
        }

        if (strpos($modifiers, '~') !== false) {
            self::$enabledMode = self::MODE_WHITESPACE;
        }

        if (!array_key_exists(self::$enabledMode, self::$renderers)) {
            $renderer = self::$renderers[self::MODE_PLAIN];
        } else {
            $renderer = self::$renderers[self::$enabledMode];
        }

        // process modifiers: @, +, ! and -
        if (strpos($modifiers, '-') !== false) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }
        if (strpos($modifiers, '!') !== false) {
            self::$expandedByDefault = true;
        }
        if (strpos($modifiers, '+') !== false) {
            self::$maxLevels = false;
        }
        if (strpos($modifiers, '@') !== false) {
            self::$returnOutput = true;
        }

        $renderer = new $renderer($names, $modifiers, $callee, $miniTrace, $caller);

        $output = call_user_func(array($renderer, 'preRender'));

        $parser = new Kint_Parser(empty($caller['class']) ? null : $caller['class']);

        if ($names === array(null) && func_num_args() === 1 && $data === 1) { // Kint::dump(1) shorthand
            $trace = Kint_Parser_Plugin_Trace::trimTrace(debug_backtrace(true));
            $lastframe = array_shift($trace);
            $trace = $parser->parse($trace, Kint_Object::blank('debug_backtrace()'));
            $trace->name = $lastframe['function'].'(1)';
            if (isset($lastframe['class'], $lastframe['type'])) {
                $trace->name = $lastframe['class'].$lastframe['type'].$trace->name;
            }
            $output .= call_user_func(array($renderer, 'render'), $trace);
        } else {
            $data = func_get_args();
            if ($data === array()) {
                $output .= call_user_func(array($renderer, 'render'), new Kint_Object_Nothing());
            }
            foreach ($data as $i => $argument) {
                $output .= call_user_func(array($renderer, 'render'), $parser->parse($argument, Kint_Object::blank(isset($names[$i]) ? $names[$i] : null)));
            }
        }

        $output .= call_user_func(array($renderer, 'postRender'));

        if (self::$returnOutput) {
            self::settings($stash);

            return $output;
        }

        if (self::$delayedMode) {
            self::settings($stash);
            register_shutdown_function('printf', '%s', $output);

            return '';
        }

        self::settings($stash);
        echo $output;

        return '';
    }

    /**
     * generic path display callback, can be configured in appRootDirs; purpose is
     * to show relevant path info and hide as much of the path as possible.
     *
     * @param string $file
     *
     * @return string
     */
    public static function shortenPath($file)
    {
        $file = str_replace('\\', '/', $file);
        $shortenedName = $file;
        $replaced = false;
        if (is_array(self::$appRootDirs)) {
            foreach (self::$appRootDirs as $path => $replaceString) {
                if (empty($path)) {
                    continue;
                }

                $path = str_replace('\\', '/', $path);

                if (strpos($file, $path) === 0) {
                    $shortenedName = $replaceString.substr($file, strlen($path));
                    $replaced = true;
                    break;
                }
            }
        }

        // fallback to find common path with Kint dir
        if (!$replaced) {
            $pathParts = explode('/', str_replace('\\', '/', dirname(dirname(__FILE__))));
            $fileParts = explode('/', $file);
            $i = 0;
            foreach ($fileParts as $i => $filePart) {
                if (!isset($pathParts[ $i ]) || $pathParts[ $i ] !== $filePart) {
                    break;
                }
            }

            $shortenedName = ($i ? '.../' : '').implode('/', array_slice($fileParts, $i));
        }

        return $shortenedName;
    }

    public static function getIdeLink($file, $line)
    {
        return str_replace(array('%f', '%l'), array($file, $line), self::$fileLinkFormat);
    }

    /**
     * returns parameter names that the function was passed, as well as any predefined symbols before function
     * call (modifiers).
     *
     * @param array $trace
     *
     * @return array($parameters, $modifier, $callee, $caller, $miniTrace)
     */
    private static function _getCalleeInfo($trace)
    {
        $miniTrace = array();

        foreach ($trace as $index => $frame) {
            if ($frame['function'] === 'spl_autoload_call' && !isset($frame['object']) && !isset($frame['class'])) {
                continue;
            }

            $miniTrace[] = $frame;
        }

        $miniTrace = Kint_Parser_Plugin_Trace::trimTrace($miniTrace);
        $callee = reset($miniTrace);
        $caller = next($miniTrace);

        unset($miniTrace[0]);

        foreach ($miniTrace as $index => &$frame) {
            if (!isset($frame['file'], $frame['line'])) {
                unset($miniTrace[$index]);
            } else {
                unset($frame['object'], $frame['args']);
            }
        }

        $miniTrace = array_values($miniTrace);

        if (!isset($callee['file']) || !is_readable($callee['file'])) {
            return array(null, null, $callee, $caller, $miniTrace);
        }

        // open the file and read it up to the position where the function call expression ended
        $file = fopen($callee['file'], 'r');
        $line = 0;
        $source = '';
        while (($row = fgets($file)) !== false) {
            if (++$line > $callee['line']) {
                break;
            }
            $source .= $row;
        }
        fclose($file);
        $source = self::_removeAllButCode($source);

        if (empty($callee['class'])) {
            $codePattern = $callee['function'];
        } else {
            if ($callee['type'] === '::') {
                $codePattern = $callee['class']."\x07*".$callee['type']."\x07*".$callee['function'];
            } else {
                /*if ( $callee['type'] === '->' )*/
                $codePattern = ".*\x07*".$callee['type']."\x07*".$callee['function'];
            }
        }

        // todo if more than one call in one line - not possible to determine variable names
        // todo does not recognize string concat
        // get the position of the last call to the function
        preg_match_all("
            [
            # beginning of statement
            [\x07{(]

            # search for modifiers (group 1)
            ([-+!@~]*)?

            # spaces
            \x07*

            # check if output is assigned to a variable (group 2) todo: does not detect concat
            (
                \\$[a-z0-9_]+ # variable
                \x07*\\.?=\x07*  # assignment
            )?

            # possibly a namespace symbol
            \\\\?

            # spaces again
            \x07*

            # main call to Kint
            ({$codePattern})

            # spaces everywhere
            \x07*

            # find the character where kint's opening bracket resides (group 3)
            (\\()

            ]ix",
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $modifiers = end($matches[1]);
        $assignment = end($matches[2]);
        $callToKint = end($matches[3]);
        $bracket = end($matches[4]);

        if (empty($callToKint)) {
            // if a wrapper is misconfigured, don't display the whole file as variable name
            return array(array(), $modifiers, $callee, $caller, $miniTrace);
        }

        $modifiers = $modifiers[0];
        if ($assignment[1] !== -1) {
            $modifiers .= '@';
        }

        $paramsString = preg_replace("[\x07+]", ' ', substr($source, $bracket[1] + 1));
        // we now have a string like this:
        // <parameters passed>); <the rest of the last read line>

        // remove everything in brackets and quotes, we don't need nested statements nor literal strings which would
        // only complicate separating individual arguments
        $c = strlen($paramsString);
        $inString = $escaped = $openedBracket = $closingBracket = false;
        $i = 0;
        $inBrackets = 0;
        $openedBrackets = array();

        while ($i < $c) {
            $letter = $paramsString[ $i ];

            if (!$inString) {
                if ($letter === '\'' || $letter === '"') {
                    $inString = $letter;
                } elseif ($letter === '(' || $letter === '[') {
                    ++$inBrackets;
                    $openedBrackets[] = $openedBracket = $letter;
                    $closingBracket = $openedBracket === '(' ? ')' : ']';
                } elseif ($inBrackets && $letter === $closingBracket) {
                    --$inBrackets;
                    array_pop($openedBrackets);
                    $openedBracket = end($openedBrackets);
                    $closingBracket = $openedBracket === '(' ? ')' : ']';
                } elseif (!$inBrackets && $letter === ')') {
                    $paramsString = substr($paramsString, 0, $i);
                    break;
                }
            } elseif ($letter === $inString && !$escaped) {
                $inString = false;
            }

            // replace whatever was inside quotes or brackets with untypeable characters, we don't
            // need that info. We'll later replace the whole string with '...'
            if ($inBrackets > 0) {
                if ($inBrackets > 1 || $letter !== $openedBracket) {
                    $paramsString[ $i ] = "\x07";
                }
            }
            if ($inString) {
                if ($letter !== $inString || $escaped) {
                    $paramsString[ $i ] = "\x07";
                }
            }

            $escaped = !$escaped && ($letter === '\\');
            ++$i;
        }

        // by now we have an un-nested arguments list, lets make it to an array for processing further
        $arguments = explode(',', preg_replace("[\x07+]", '...', $paramsString));

        // test each argument whether it was passed literary or was it an expression or a variable name
        $parameters = array();
        $blacklist = array('null', 'true', 'false', 'array(...)', 'array()', '"..."', '[...]', 'b"..."');
        foreach ($arguments as $argument) {
            $argument = trim($argument);

            if (is_numeric($argument)
                || in_array(str_replace("'", '"', strtolower($argument)), $blacklist, true)
            ) {
                $parameters[] = null;
            } else {
                $parameters[] = $argument;
            }
        }

        return array($parameters, $modifiers, $callee, $caller, $miniTrace);
    }

    /**
     * removes comments and zaps whitespace & <?php tags from php code, makes for easier further parsing.
     *
     * @param string $source
     *
     * @return string
     */
    private static function _removeAllButCode($source)
    {
        $commentTokens = array(
            T_COMMENT => true, T_INLINE_HTML => true, T_DOC_COMMENT => true,
        );
        $whiteSpaceTokens = array(
            T_WHITESPACE => true, T_CLOSE_TAG => true,
            T_OPEN_TAG => true, T_OPEN_TAG_WITH_ECHO => true,
        );

        $cleanedSource = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (isset($commentTokens[ $token[0] ])) {
                    continue;
                }

                if (isset($whiteSpaceTokens[ $token[0] ])) {
                    $token = "\x07";
                } else {
                    $token = $token[1];
                }
            } elseif ($token === ';') {
                $token = "\x07";
            }

            $cleanedSource .= $token;
        }

        return $cleanedSource;
    }
}