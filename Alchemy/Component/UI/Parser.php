<?php
namespace Alchemy\Component\UI;

/**
 * Class Parser
 *
 * @version   1.0
 * @author    Erik Amaru Ortiz <aortiz.erik@gmail.com>
 * @link      https://github.com/eriknyk/phpalchemy
 * @copyright Copyright 2012 Erik Amaru Ortiz
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 * @package   Alchemy/Component/Routing
 */
class Parser
{
    protected $file   = '';
    protected $defs   = array();
    protected $blocks = array();
    protected $defaultBlock = '';

    const T_DEF      = 'def';
    const T_VAR      = 'var';
    const T_BLOCK    = 'block';
    const T_END      = 'end';
    const T_ITERATOR = 'iterator';
    const T_GLOBAL   = 'global';

    public function __construct($file)
    {
        $this->file = $file;

        $this->load();
    }

    protected function load()
    {
        if (!is_file($this->file)) {
            throw new \Exception(sprintf(
                'Template "%s" file doesn\'t exist!', $this->file
            ));
        }

        $fp = fopen($this->file, 'r');

        $lineCount       = 0;
        $nextToken       = '';
        $block           = '';
        $currentValue    = '';
        $stringComposing = false;

        while (($line = fgets($fp)) !== false) {
            $lineCount++;

            if ($stringComposing) {
                if (substr($line, 0, 3) === '>>>') {
                    $this->blocks[$block][$name] = $value;
                    $block = '';
                    $value = '';
                    $stringComposing = false;
                } else {
                    $value .= $line;
                }

                continue;
            }

            $line = trim($line);

            if (substr(trim($line), 0, 1) == '#' || $line === '') {
                continue; //skip comments
            }

            $kwPattern = '^@(?<keyword>\w+)';

            if (!preg_match('/'.$kwPattern.'.*/', $line, $matches)) {
                throw new \Exception(sprintf(
                    'Parse Error: Unknow keyword, lines must starts with a valid keyword, near: %s, on line %s',
                    $line, $lineCount
                ));
            }

            $keyword = $matches['keyword'];

            switch ($keyword) {
                case Parser::T_DEF:
                    $pattern = '/'.$kwPattern.'\s+(?<type>[\w]+)\s+(?<name>[\w.]+)\s+(?<value>.+)/';

                    if (!preg_match($pattern, $line, $matches)) {
                        throw new \Exception(sprintf(
                            "Syntax Error: near: '%s', on line %s.\n" .
                            "Syntax should be: @def <type> <name> <value>",
                            $line, $lineCount
                        ));
                    }

                    $keyword = $matches['keyword'];
                    $type    = $matches['type'];
                    $name    = $matches['name'];
                    $value   = $matches['value'];

                    $tmp     = explode('.', $name);
                    $defName = $tmp[0];
                    $defProp = isset($tmp[1]) ? $tmp[1] : '';

                    switch ($type) {
                        case Parser::T_GLOBAL:
                        case Parser::T_ITERATOR:

                            if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                                $value = trim($value, '"');
                            } elseif (substr($value, 0, 1) == "'" && substr($value, -1) == "'") {
                                $value = trim($value, "'");
                            }

                            $value = self::castValue($value);

                            if (empty($defProp)) {
                                $this->defs[$type][$defName] = $value;
                            } else {
                                $this->defs[$type][$defName][$defProp] = $value;
                            }
                            break;

                        default:
                            throw new \Exception(sprintf(
                                'Parse Error: unknow definition type: "%s" on line %s.', $type, $lineCount
                            ));
                    }
                    break;

                case Parser::T_BLOCK:
                    $pattern = '/'.$kwPattern.'\s+(?<block>[\w]+)/';

                    if (!preg_match($pattern, $line, $matches)) {
                        throw new Exception(sprintf(
                            "Parse Error: Syntax error near: %s, on line %s.\n." .
                            "Syntax should be: @def <type> <name> <value>",
                            substr($line, 0, 20).'...', $lineCount
                        ));
                    }
                    $block = $matches['block'];

                    if (!empty($nextToken)) {
                        throw new \Exception(sprintf(
                            'Parse Error: expected: @"%s", given: @"%s"', $nextToken, Parser::T_BLOCK
                        ));
                    }

                    $nextToken = Parser::T_END;
                    break;

                case Parser::T_END:
                    if (empty($nextToken)) {
                        throw new \Exception(sprintf(
                            'Parse Error: close keyword: @"%s" given, but any block was started.', Parser::T_BLOCK
                        ));
                    }

                    if ($nextToken !== Parser::T_END) {
                        throw new \Exception(sprintf(
                            'Parse Error: expected: @"%s", given: @"%s"', $nextToken, Parser::T_END
                        ));
                    }

                    $nextToken = '';
                    break;

                case Parser::T_VAR:
                    $pattern = '/'.$kwPattern.'\s+(?<name>[\w]+)\s+(?<value>.+)/';

                    if (!preg_match($pattern, $line, $matches)) {
                        throw new Exception(sprintf(
                            "Parse Error: Syntax error near: %s, on line %s.\n." .
                            "Syntax should be: @var <name> <value>\n or \n" .
                            "@var <name> <<<\nsome large string\nmultiline...\n>>>\n\n",
                            substr($line, 0, 20).'...', $lineCount
                        ));
                    }

                    $keyword = $matches['keyword'];
                    $name    = $matches['name'];
                    $value   = $matches['value'];

                    if (substr($value, 0, 3) === '<<<' && $value !== '<<<') {
                        throw new \Exception(sprintf(
                            "Syntax Error: multiline string must starts on new line after open braces <<<\n" .
                            "near: '%s', on line %s", $line, $lineCount
                        ));
                    }

                    if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                        $value = trim($value, '"');
                    } elseif (substr($value, 0, 1) == "'" && substr($value, -1) == "'") {
                        $value = trim($value, "'");
                    }

                    if ($value !== '<<<') {
                        $this->blocks[$block][$name] = self::castValue($value);
                    } else {
                        $value = '';
                        $stringComposing = true;
                    }
                    break;

                default:
                    throw new \Exception(sprintf(
                        'Parse Error: unknow definition type: %s, on line %s', $type, $lineCount
                    ));
            }

        }

        if ($stringComposing) {
            throw new \Exception(sprintf(
                "Parse Error: Multiline string closing braces are missing '>>>',\nfor @block: '%s', @var: '%s' " .
                "until end of file.", $block, $name
            ));
        }
    }

    public function getGlobals()
    {
        return $this->defs[Parser::T_GLOBAL];
    }

    public function getIterators()
    {
        return $this->defs[Parser::T_ITERATOR];
    }

    public function getBlocks()
    {
        return $this->blocks;
    }

    public function getDef($name)
    {
        if (!isset($this->defs[$name])) {
            return false;
        }

        return $this->defs[$name];
    }

    public function getBlock($name)
    {
        if (isset($this->blocks[$name])) {
            return $this->blocks[$name];
        }

        if (!empty($this->defaultBlock)) {
            return $this->blocks[$this->defaultBlock];
        }

        throw new \InvalidArgumentException(sprintf('Error: Undefined template block: "%s"', $name));
    }

    public function setDefaultBlock($block)
    {
        if (!isset($this->blocks[$block])) {
            throw new \InvalidArgumentException(sprintf('Error: trying set as default to undefined block: "%s"', $block));
        }

        $this->defaultBlock = $block;
    }

    public function generate($name, $data)
    {
        $block = $this->getBlock($name);
        $template = $block['template'];

        $content = $this->buildIterators($template, $data);
        $content = $this->replaceData($content, $data);

        return $content;
    }

    /*
     * PRIVATE/PROTECTED METHODS
     */

    private static function castValue($val)
    {
        if (is_array($val)) {
            foreach ($val as $key => $value) {
                $val[$key] = self::castValue($value);
            }
        } elseif (is_string($val)) {
            $tmp = strtolower($val);

            if ($tmp === 'false' || $tmp === 'true') {
                $val = $tmp === 'true';
            } elseif (is_numeric($val)) {
                return $val + 0;
            }
        }

        return $val;
    }

    protected function buildIterators($template, $data)
    {
        $pattern    = '/@@(?<iterator>\w+)\(\{(?<var>\w+)\}\)/';
        $iterators  = $this->getIterators();

        $result = preg_replace_callback(
            $pattern,
            function($matches) use ($data, $iterators) {
                if (!isset($iterators[$matches['iterator']])) {
                    throw new Exception(sprintf(
                        'Parse Error: Trying to use undefinded iterator "%s"',
                        $matches['iterator']
                    ));
                }

                $iterator = $iterators[$matches['iterator']];

                $composed = array();
                $data = $data[$matches['var']];

                foreach ($data as $key => $value) {
                    $str = str_replace('{_key}', $key, $iterator['tpl']);
                    $str = str_replace('{_value}', $value, $str);
                    $composed[] = $str;
                }

                return implode($iterator['sep'], $composed);
            },
            $template
        );

        return $result;
    }

    protected function replaceData($template, $data)
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $template = str_replace('{'.$key.'}', $value, $template);
            }
        }

        return $template;
    }
}