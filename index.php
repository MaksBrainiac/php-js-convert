<?php

use PhpParser\ParserFactory;
use Symfony\Component\VarExporter\VarExporter;

error_reporting(E_ALL);
ini_set("display_errors", 1);

require 'vendor/autoload.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$error = '';
$js = '';
$php = '';
$compact = !empty($_POST['compact']);

if (isset($_POST['to_php'])) {
    try {
        $js = trim($_POST['js']);
        $dec = CJSON::decode($js, true);
        if ($compact)
            $php = min_var_export($dec);
        else
            $php = VarExporter::export($dec);
    } catch (Throwable $t) {
        $error = $t->getMessage();
    }
}

if (isset($_POST['to_js'])) {
    $php = str_replace("\r\n", "\n", trim($_POST['php']));

    try {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $cc = $parser->parse('<?php $result = ' . PHP_EOL . $php . '; ?>');
        $re = nodeToRes($cc[0]->expr->expr);
        $js = json_encode($re, JSON_UNESCAPED_UNICODE | ($compact ? 0 : JSON_PRETTY_PRINT));
    } catch (Throwable $t) {
        $error = $t->getMessage();
    }
}

function isAssoc(array $arr)
{
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function min_var_export($input) {
    if(is_array($input)) {
        $buffer = [];
        if (isAssoc($input))
            foreach($input as $key => $value)
                $buffer[] = var_export($key, true)."=>".min_var_export($value);
        else
            foreach($input as $key => $value)
                $buffer[] = min_var_export($value);
        return "[".implode(",",$buffer)."]";
    } else
        return var_export($input, true);
}

function nodeToRes($item) {
    $cf = [
        'null' => null,
        'true' => true,
        'false' => false,
    ];
    if ($item instanceof \PhpParser\Node\Expr\ConstFetch)
        return $cf[$item->name->parts[0]];
    if ($item instanceof \PhpParser\Node\Scalar)   
        return $item->value;
    if ($item instanceof \PhpParser\Node\Expr\Array_)
        return nodesToArray($item);

    /** @var PhpParser\Node\Expr\BinaryOp\Concat $item */
    if ($item instanceof PhpParser\Node\Expr\BinaryOp\Concat) {
        return nodeToRes($item->left) . nodeToRes($item->right);
    }
    
    throw new Exception('Unsupported node ' . get_class($item) . ' on line #' . ($item->getStartLine() - 1));   
    //    return nodesToArray($item);
    dd(get_class($item), $item);
    //return nodeToRes($item->value); // ??
}

function nodesToArray(PhpParser\Node\Expr\Array_ $arr) {
    $res = [];
    $index = 0;
    foreach ($arr->items as $item) {
        $res[$item->key ? $item->key->value : $index++] = nodeToRes($item->value); 
    }
    return $res;
}

/**
 * JSON (JavaScript Object Notation) is a lightweight data-interchange
 * format. It is easy for humans to read and write. It is easy for machines
 * to parse and generate. It is based on a subset of the JavaScript
 * Programming Language, Standard ECMA-262 3rd Edition - December 1999.
 * This feature can also be found in  Python. JSON is a text format that is
 * completely language independent but uses conventions that are familiar
 * to programmers of the C-family of languages, including C, C++, C#, Java,
 * JavaScript, Perl, TCL, and many others. These properties make JSON an
 * ideal data-interchange language.
 *
 * This package provides a simple encoder and decoder for JSON notation. It
 * is intended for use with client-side Javascript applications that make
 * use of HTTPRequest to perform server communication functions - data can
 * be encoded into JSON notation for use in a client-side javascript, or
 * decoded from incoming Javascript requests. JSON format is native to
 * Javascript, and can be directly eval()'ed with no further parsing
 * overhead
 *
 * All strings should be in ASCII or UTF-8 format!
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @author      Michal Migurski <mike-json@teczno.com>
 * @author      Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright   2005 Michal Migurski
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

/**
 * CJSON converts PHP data to and from JSON format.
 *
 * @author     Michal Migurski <mike-json@teczno.com>
 * @author     Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author     Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @package    system.web.helpers
 * @since 1.0
 */
class CJSON
{
    /**
     * Marker constant for JSON::decode(), used to flag stack state
     */
    const JSON_SLICE = 1;

    /**
     * Marker constant for JSON::decode(), used to flag stack state
     */
    const JSON_IN_STR = 2;

    /**
     * Marker constant for JSON::decode(), used to flag stack state
     */
    const JSON_IN_ARR = 4;

    /**
     * Marker constant for JSON::decode(), used to flag stack state
     */
    const JSON_IN_OBJ = 8;

    /**
     * Marker constant for JSON::decode(), used to flag stack state
     */
    const JSON_IN_CMT = 16;

    /**
     * Encodes an arbitrary variable into JSON format
     *
     * @param mixed $var any number, boolean, string, array, or object to be encoded.
     * If var is a string, it will be converted to UTF-8 format first before being encoded.
     * @return string JSON string representation of input var
     */
    public static function encode($var)
    {
        switch (gettype($var)) {
            case 'boolean':
                return $var ? 'true' : 'false';

            case 'NULL':
                return 'null';

            case 'integer':
                return (int)$var;

            case 'double':
            case 'float':
                return str_replace(',', '.', (float)$var); // locale-independent representation

            case 'string':
                ///if (($enc = strtoupper(Yii::app()->charset)) !== 'UTF-8')
                ///    $var = iconv($enc, 'UTF-8', $var);

                if (function_exists('json_encode'))
                    return json_encode($var);

                // STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
                $ascii = '';
                $strlen_var = strlen($var);

                /*
                 * Iterate over every character in the string,
                 * escaping with a slash or encoding to UTF-8 where necessary
                 */
                for ($c = 0; $c < $strlen_var; ++$c) {

                    $ord_var_c = ord($var{$c});

                    switch (true) {
                        case $ord_var_c == 0x08:
                            $ascii .= '\b';
                            break;
                        case $ord_var_c == 0x09:
                            $ascii .= '\t';
                            break;
                        case $ord_var_c == 0x0A:
                            $ascii .= '\n';
                            break;
                        case $ord_var_c == 0x0C:
                            $ascii .= '\f';
                            break;
                        case $ord_var_c == 0x0D:
                            $ascii .= '\r';
                            break;

                        case $ord_var_c == 0x22:
                        case $ord_var_c == 0x2F:
                        case $ord_var_c == 0x5C:
                            // double quote, slash, slosh
                            $ascii .= '\\' . $var{$c};
                            break;

                        case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
                            // characters U-00000000 - U-0000007F (same as ASCII)
                            $ascii .= $var{$c};
                            break;

                        case (($ord_var_c & 0xE0) == 0xC0):
                            // characters U-00000080 - U-000007FF, mask 110XXXXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c, ord($var{$c + 1}));
                            $c += 1;
                            $utf16 = self::utf8ToUTF16BE($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xF0) == 0xE0):
                            // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                ord($var{$c + 1}),
                                ord($var{$c + 2}));
                            $c += 2;
                            $utf16 = self::utf8ToUTF16BE($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xF8) == 0xF0):
                            // characters U-00010000 - U-001FFFFF, mask 11110XXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                ord($var{$c + 1}),
                                ord($var{$c + 2}),
                                ord($var{$c + 3}));
                            $c += 3;
                            $utf16 = self::utf8ToUTF16BE($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xFC) == 0xF8):
                            // characters U-00200000 - U-03FFFFFF, mask 111110XX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                ord($var{$c + 1}),
                                ord($var{$c + 2}),
                                ord($var{$c + 3}),
                                ord($var{$c + 4}));
                            $c += 4;
                            $utf16 = self::utf8ToUTF16BE($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xFE) == 0xFC):
                            // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                ord($var{$c + 1}),
                                ord($var{$c + 2}),
                                ord($var{$c + 3}),
                                ord($var{$c + 4}),
                                ord($var{$c + 5}));
                            $c += 5;
                            $utf16 = self::utf8ToUTF16BE($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;
                    }
                }

                return '"' . $ascii . '"';

            case 'array':
                /*
                 * As per JSON spec if any array key is not an integer
                 * we must treat the the whole array as an object. We
                 * also try to catch a sparsely populated associative
                 * array with numeric keys here because some JS engines
                 * will create an array with empty indexes up to
                 * max_index which can cause memory issues and because
                 * the keys, which may be relevant, will be remapped
                 * otherwise.
                 *
                 * As per the ECMA and JSON specification an object may
                 * have any string as a property. Unfortunately due to
                 * a hole in the ECMA specification if the key is a
                 * ECMA reserved word or starts with a digit the
                 * parameter is only accessible using ECMAScript's
                 * bracket notation.
                 */

                // treat as a JSON object
                if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
                    return '{' .
                        join(',', array_map(array('CJSON', 'nameValue'),
                            array_keys($var),
                            array_values($var)))
                        . '}';
                }

                // treat it like a regular array
                return '[' . join(',', array_map(array('CJSON', 'encode'), $var)) . ']';

            case 'object':
                if ($var instanceof Traversable) {
                    $vars = array();
                    foreach ($var as $k => $v)
                        $vars[$k] = $v;
                } else
                    $vars = get_object_vars($var);
                return '{' .
                    join(',', array_map(array('CJSON', 'nameValue'),
                        array_keys($vars),
                        array_values($vars)))
                    . '}';

            default:
                return '';
        }
    }

    /**
     * array-walking function for use in generating JSON-formatted name-value pairs
     *
     * @param string $name name of key to use
     * @param mixed $value reference to an array element to be encoded
     *
     * @return   string  JSON-formatted name-value pair, like '"name":value'
     * @access   private
     */
    protected static function nameValue($name, $value)
    {
        return self::encode(strval($name)) . ':' . self::encode($value);
    }

    /**
     * reduce a string by removing leading and trailing comments and whitespace
     *
     * @param string $str string value to strip of comments and whitespace
     *
     * @return string string value stripped of comments and whitespace
     * @access   private
     */
    protected static function reduceString($str)
    {
        $str = preg_replace(array(

            // eliminate single line comments in '// ...' form
            '#^\s*//(.+)$#m',

            // eliminate multi-line comments in '/* ... */' form, at start of string
            '#^\s*/\*(.+)\*/#Us',

            // eliminate multi-line comments in '/* ... */' form, at end of string
            '#/\*(.+)\*/\s*$#Us'

        ), '', $str);

        // eliminate extraneous space
        return trim($str);
    }

    /**
     * decodes a JSON string into appropriate variable
     *
     * @param string $str JSON-formatted string
     * @param boolean $useArray whether to use associative array to represent object data
     * @return mixed   number, boolean, string, array, or object corresponding to given JSON input string.
     *    Note that decode() always returns strings in ASCII or UTF-8 format!
     * @access   public
     */
    public static function decode($str, $useArray = true)
    {
        if (function_exists('json_decode')) {
            $json = json_decode($str, $useArray);

            // based on investigation, native fails sometimes returning null.
            // see: http://gggeek.altervista.org/sw/article_20070425.html
            // As of PHP 5.3.6 it still fails on some valid JSON strings
            if ($json !== null)
                return $json;
        }

        $str = self::reduceString($str);

        switch (strtolower($str)) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            default:
                if (is_numeric($str)) {
                    // Lookie-loo, it's a number

                    // This would work on its own, but I'm trying to be
                    // good about returning integers where appropriate:
                    // return (float)$str;

                    // Return float or int, as appropriate
                    return ((float)$str == (integer)$str)
                        ? (integer)$str
                        : (float)$str;

                } elseif (preg_match('/^("|\').+(\1)$/s', $str, $m) && $m[1] == $m[2]) {
                    // STRINGS RETURNED IN UTF-8 FORMAT
                    $delim = substr($str, 0, 1);
                    $chrs = substr($str, 1, -1);
                    $utf8 = '';
                    $strlen_chrs = strlen($chrs);

                    for ($c = 0; $c < $strlen_chrs; ++$c) {

                        $substr_chrs_c_2 = substr($chrs, $c, 2);
                        $ord_chrs_c = ord($chrs{$c});

                        switch (true) {
                            case $substr_chrs_c_2 == '\b':
                                $utf8 .= chr(0x08);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\t':
                                $utf8 .= chr(0x09);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\n':
                                $utf8 .= chr(0x0A);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\f':
                                $utf8 .= chr(0x0C);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\r':
                                $utf8 .= chr(0x0D);
                                ++$c;
                                break;

                            case $substr_chrs_c_2 == '\\"':
                            case $substr_chrs_c_2 == '\\\'':
                            case $substr_chrs_c_2 == '\\\\':
                            case $substr_chrs_c_2 == '\\/':
                                if (($delim == '"' && $substr_chrs_c_2 != '\\\'') ||
                                    ($delim == "'" && $substr_chrs_c_2 != '\\"')) {
                                    $utf8 .= $chrs{++$c};
                                }
                                break;

                            case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
                                // single, escaped unicode character
                                $utf16 = chr(hexdec(substr($chrs, ($c + 2), 2)))
                                    . chr(hexdec(substr($chrs, ($c + 4), 2)));
                                $utf8 .= self::utf16beToUTF8($utf16);
                                $c += 5;
                                break;

                            case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
                                $utf8 .= $chrs{$c};
                                break;

                            case ($ord_chrs_c & 0xE0) == 0xC0:
                                // characters U-00000080 - U-000007FF, mask 110XXXXX
                                //see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 2);
                                ++$c;
                                break;

                            case ($ord_chrs_c & 0xF0) == 0xE0:
                                // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 3);
                                $c += 2;
                                break;

                            case ($ord_chrs_c & 0xF8) == 0xF0:
                                // characters U-00010000 - U-001FFFFF, mask 11110XXX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 4);
                                $c += 3;
                                break;

                            case ($ord_chrs_c & 0xFC) == 0xF8:
                                // characters U-00200000 - U-03FFFFFF, mask 111110XX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 5);
                                $c += 4;
                                break;

                            case ($ord_chrs_c & 0xFE) == 0xFC:
                                // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 6);
                                $c += 5;
                                break;

                        }

                    }

                    return $utf8;

                } elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
                    // array, or object notation

                    if ($str{0} == '[') {
                        $stk = array(self::JSON_IN_ARR);
                        $arr = array();
                    } else {
                        if ($useArray) {
                            $stk = array(self::JSON_IN_OBJ);
                            $obj = array();
                        } else {
                            $stk = array(self::JSON_IN_OBJ);
                            $obj = new stdClass();
                        }
                    }

                    $stk[] = array('what' => self::JSON_SLICE, 'where' => 0, 'delim' => false);

                    $chrs = substr($str, 1, -1);
                    $chrs = self::reduceString($chrs);

                    if ($chrs == '') {
                        if (reset($stk) == self::JSON_IN_ARR) {
                            return $arr;

                        } else {
                            return $obj;

                        }
                    }

                    //print("\nparsing {$chrs}\n");

                    $strlen_chrs = strlen($chrs);

                    for ($c = 0; $c <= $strlen_chrs; ++$c) {

                        $top = end($stk);
                        $substr_chrs_c_2 = substr($chrs, $c, 2);

                        if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == self::JSON_SLICE))) {
                            // found a comma that is not inside a string, array, etc.,
                            // OR we've reached the end of the character list
                            $slice = substr($chrs, $top['where'], ($c - $top['where']));
                            $stk[] = array('what' => self::JSON_SLICE, 'where' => ($c + 1), 'delim' => false);
                            //print("Found split at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                            if (reset($stk) == self::JSON_IN_ARR) {
                                // we are in an array, so just push an element onto the stack
                                $arr[] = self::decode($slice, $useArray);

                            } elseif (reset($stk) == self::JSON_IN_OBJ) {
                                // we are in an object, so figure
                                // out the property name and set an
                                // element in an associative array,
                                // for now
                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // "name":value pair
                                    $key = self::decode($parts[1], $useArray);
                                    $val = self::decode($parts[2], $useArray);

                                    if ($useArray) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                } elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // name:value pair, where name is unquoted
                                    $key = $parts[1];
                                    $val = self::decode($parts[2], $useArray);

                                    if ($useArray) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                }

                            }

                        } elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != self::JSON_IN_STR)) {
                            // found a quote, and we are not inside a string
                            $stk[] = array('what' => self::JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c});
                            //print("Found start of string at {$c}\n");

                        } elseif (($chrs{$c} == $top['delim']) &&
                            ($top['what'] == self::JSON_IN_STR) &&
                            (($chrs{$c - 1} != "\\") ||
                                ($chrs{$c - 1} == "\\" && $chrs{$c - 2} == "\\"))) {
                            // found a quote, we're in a string, and it's not escaped
                            array_pop($stk);
                            //print("Found end of string at {$c}: ".substr($chrs, $top['where'], (1 + 1 + $c - $top['where']))."\n");

                        } elseif (($chrs{$c} == '[') &&
                            in_array($top['what'], array(self::JSON_SLICE, self::JSON_IN_ARR, self::JSON_IN_OBJ))) {
                            // found a left-bracket, and we are in an array, object, or slice
                            $stk[] = array('what' => self::JSON_IN_ARR, 'where' => $c, 'delim' => false);
                            //print("Found start of array at {$c}\n");

                        } elseif (($chrs{$c} == ']') && ($top['what'] == self::JSON_IN_ARR)) {
                            // found a right-bracket, and we're in an array
                            array_pop($stk);
                            //print("Found end of array at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        } elseif (($chrs{$c} == '{') &&
                            in_array($top['what'], array(self::JSON_SLICE, self::JSON_IN_ARR, self::JSON_IN_OBJ))) {
                            // found a left-brace, and we are in an array, object, or slice
                            $stk[] = array('what' => self::JSON_IN_OBJ, 'where' => $c, 'delim' => false);
                            //print("Found start of object at {$c}\n");

                        } elseif (($chrs{$c} == '}') && ($top['what'] == self::JSON_IN_OBJ)) {
                            // found a right-brace, and we're in an object
                            array_pop($stk);
                            //print("Found end of object at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        } elseif (($substr_chrs_c_2 == '/*') &&
                            in_array($top['what'], array(self::JSON_SLICE, self::JSON_IN_ARR, self::JSON_IN_OBJ))) {
                            // found a comment start, and we are in an array, object, or slice
                            $stk[] = array('what' => self::JSON_IN_CMT, 'where' => $c, 'delim' => false);
                            $c++;
                            //print("Found start of comment at {$c}\n");

                        } elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == self::JSON_IN_CMT)) {
                            // found a comment end, and we're in one now
                            array_pop($stk);
                            $c++;

                            for ($i = $top['where']; $i <= $c; ++$i)
                                $chrs = substr_replace($chrs, ' ', $i, 1);

                            //print("Found end of comment at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        }

                    }

                    if (reset($stk) == self::JSON_IN_ARR) {
                        return $arr;

                    } elseif (reset($stk) == self::JSON_IN_OBJ) {
                        return $obj;

                    }

                }
        }
    }

    /**
     * This function returns any UTF-8 encoded text as a list of
     * Unicode values:
     * @param string $str string to convert
     * @return string
     * @author Scott Michael Reynen <scott@randomchaos.com>
     * @link   http://www.randomchaos.com/document.php?source=php_and_unicode
     * @see    unicodeToUTF8()
     */
    protected static function utf8ToUnicode(&$str)
    {
        $unicode = array();
        $values = array();
        $lookingFor = 1;

        for ($i = 0; $i < strlen($str); $i++) {
            $thisValue = ord($str[$i]);
            if ($thisValue < 128)
                $unicode[] = $thisValue;
            else {
                if (count($values) == 0)
                    $lookingFor = ($thisValue < 224) ? 2 : 3;
                $values[] = $thisValue;
                if (count($values) == $lookingFor) {
                    $number = ($lookingFor == 3) ?
                        (($values[0] % 16) * 4096) + (($values[1] % 64) * 64) + ($values[2] % 64) :
                        (($values[0] % 32) * 64) + ($values[1] % 64);
                    $unicode[] = $number;
                    $values = array();
                    $lookingFor = 1;
                }
            }
        }
        return $unicode;
    }

    /**
     * This function converts a Unicode array back to its UTF-8 representation
     * @param string $str string to convert
     * @return string
     * @author Scott Michael Reynen <scott@randomchaos.com>
     * @link   http://www.randomchaos.com/document.php?source=php_and_unicode
     * @see    utf8ToUnicode()
     */
    protected static function unicodeToUTF8(&$str)
    {
        $utf8 = '';
        foreach ($str as $unicode) {
            if ($unicode < 128) {
                $utf8 .= chr($unicode);
            } elseif ($unicode < 2048) {
                $utf8 .= chr(192 + (($unicode - ($unicode % 64)) / 64));
                $utf8 .= chr(128 + ($unicode % 64));
            } else {
                $utf8 .= chr(224 + (($unicode - ($unicode % 4096)) / 4096));
                $utf8 .= chr(128 + ((($unicode % 4096) - ($unicode % 64)) / 64));
                $utf8 .= chr(128 + ($unicode % 64));
            }
        }
        return $utf8;
    }

    /**
     * UTF-8 to UTF-16BE conversion.
     *
     * Maybe really UCS-2 without mb_string due to utf8ToUnicode limits
     * @param string $str string to convert
     * @param boolean $bom whether to output BOM header
     * @return string
     */
    protected static function utf8ToUTF16BE(&$str, $bom = false)
    {
        $out = $bom ? "\xFE\xFF" : '';
        if (function_exists('mb_convert_encoding'))
            return $out . mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');

        $uni = self::utf8ToUnicode($str);
        foreach ($uni as $cp)
            $out .= pack('n', $cp);
        return $out;
    }

    /**
     * UTF-8 to UTF-16BE conversion.
     *
     * Maybe really UCS-2 without mb_string due to utf8ToUnicode limits
     * @param string $str string to convert
     * @return string
     */
    protected static function utf16beToUTF8(&$str)
    {
        $uni = unpack('n*', $str);
        return self::unicodeToUTF8($uni);
    }
}

?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8">
    <title>Array Syntax Convertor</title>
    <style>
        body {
            margin: 0px;
            padding: 0px;
            background: #F7F7F7;
            font: 36px "Segoe UI";
        }
        body, html {
            height: 100%;
        }
        input, .x {
            font: 36px "Segoe UI Light";
        }
        .all {

        }
        #error {
            border: 2px solid red; 
            padding: 5px; 
            margin: 3px; 
            background: white;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div style="margin: 0 auto; max-width: 1600px">
        <div id="error" style="<?php if (!$error): ?>display: none<?php endif; ?>">
            <?= htmlspecialchars($error) ?>
        </div>
        <table height="100%" class="all" style="width: 100%; margin: 0 auto;">
            <tr>
                <td valign="center">
                    <form method="post" action="" id="f">
                        <table width="100%">
                            <tr>
                                <th style="width: 50%">JS</th>
                                <th style="width: 50%">PHP</th>
                            </tr>
                            <tr>
                                <td><textarea style="width: 100%; height: 400px" autocomplete="off" name="js" id="js"><?= htmlspecialchars($js) ?></textarea></td>
                                <td><textarea style="width: 100%; height: 400px" autocomplete="off" name="php" id="php"><?= htmlspecialchars($php) ?></textarea></td>
                            </tr>
                            <tr>
                                <td style="text-align: center"><input type="submit" name="to_php" value="Convert to PHP" /></td>
                                <td style="text-align: center"><input type="submit" name="to_js" value="Convert to JS" /></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="text-align: center">
                                    <label class="x"><input type="checkbox" id="compact" name="compact" value="1" <?php if ($compact): ?> checked <?php endif; ?> /> compact</label>
                                </td>
                            </tr>
                        </table>
                    </form>
                </td>
            </tr>
        </table>
    </div>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script type="text/javascript">
        $(function(){
            <?php if ($isPost): ?>
                localStorage.js = <?= json_encode($js); ?>;
                localStorage.php = <?= json_encode($php); ?>;
                localStorage.compact = <?= $compact ? 1 : 0 ?>;
            <?php else: ?>
                if (localStorage.js)
                    $("#js").val(localStorage.js);
                if (localStorage.php)
                    $("#php").val(localStorage.php);
                if (localStorage.compact !== undefined)
                    $("#compact").prop('checked', +localStorage.compact === 1);
            <?php endif; ?>
            $("form input[type=submit]").click(function() {
                $("input[type=submit]", $(this).parents("form")).removeAttr("clicked");
                $(this).attr("clicked", "true");
            });
            $('#f').on('submit', function($event){
                localStorage.js = $("#js").val();
                localStorage.php = $("#php").val();
                localStorage.compact = $("#compact").val();

                var val = $("input[type=submit][clicked=true]").attr('name');
                if (val === 'to_php') {
                    try {
                        var val = JSON.parse($('#js').val().trim());
                    } catch (e) {
                        $('#error').show().text("" + e);
                        $event.preventDefault();
                    }
                } else {
                 
                }
            });
        });
    </script>
</body>
</html>