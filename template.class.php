<?php

/** 
 * PHP Template class - developed with love by Matheus Felipe Marques
 * 
 * CHANGELOG
 * 2021-02-15 -> Class created.
 * 2021-03-15 -> Defined vars definition, loadFile function, if statements.
 * 2021-03-16 -> Applied the ifs and loadfile replacements. Added block functions and statements for the template, including loop.
 * 2021-03-18 -> Applied the setFunction statements, to call functions using a Template syntax on the HTML.
 * 2021-03-19 -> Improved template rendering methods, removing excess of empty spaces between tags. Added the "code" method, which concatenates the given HTML code with the current template/block HTML code. Added template data about rendering and memory usage.
 * 2021-03-22 -> Improved recursive loadFile method to add support with differents DIRECTORY_SEPARATORs. Now it will include the file wheter it is a Windows server os a GNU server.
 * 2021-03-23 -> Improved prepareIfs method to allow underscores (_) on the if definition. Added the prepareDocument method, to remove linebreaks and extra empty spaces on methods that sets code on the HTML. Improved setBlock Method. Improved loadFile method to be able to load multiple files on same HTML string. Improved recursively <_template:block()> parameters
 * 2021-06-06 -> Added $echo to render method, to make the template do the 'echo' of the HTML result
 * 2021-08-13 -> Refactored all block operations. Now it supports multiples blocks
 * 2021-08-14 -> Refactored function operations
 * 2021-08-15 -> Refactored all If operations. Now it supports multiples Ifs within multiple Blocks
 * 2021-08-16 -> Improved even more the If operations. Now it removes even when there's no IF used
 * */

class Template
{
    private $templateInit = null;
    private $templateInitialMemory = null;
    private $templateDir = ""; // this is the directory where the template files are stored
    private $HTML = "";

    private $templatePrepareDocumentRegex = "/|\r|\n|[ ]{2,}/"; // this regex will remove all line-breaks and extra empty spaces on the document. 

    private $templateIncludeRegex = "/(?:\<\_template\:loadFile\()(.*?\.html)(?:\)(?: )?(?:\/)?\/>)/"; // this regex loads other child template files inside a template files - it acts recursively

    private $templateVarRegex = "/\<\_template\:(\\$)?\\field( )?(\/)?\>|\{\{(:?\\$)?\\field\}\}/"; // \\field will be replaced on the setVar function
    private $templateVars = array(); // array with defined vars to be set on the template. To define a var you can use: <_template:$var/> <_template:var/> or {{$var}} {{var}}

    private $templateIfRegex = "/(?:\<\_template\:if\()((?:(?:\\$)?[a-zA-Z0-9_]+)(?:(?:\:)(?:\\$)?(?:[a-zA-Z0-9_]+))?)(?:\)(?: )?(?:\/)?\>)/"; // this regex is used to prepare the conditions on the template ifs
    private $templatePrepareIfRegex = "/(?:\<\_template\:if\({condition}\)\>)(.*?)(?:\<\/\_template\:if\>)/"; // this regex also is used to prepare the conditions on the template ifs
    private $templateIfsKeys = array(); // array with the "ifs" keys on the template file
    private $templateIfs = array(); // array with the "ifs" keys on the template file

    private $templateBlockRegex = "/(?:\<\_template\:block\()([a-zA-Z0-9_+]{1,})\)\>/"; // this regex will set the blocks inside the document
    private $templatePrepareBlockRegex = "/(?:\<\_template\:block\({condition}\)\>)(.*?)(\<\/\_template\:block(?: )?\>)/"; // this regex will set the blocks HTML comments on the document
    private $templateBlocksKeys = array(); // array where will be stored the block keys and names
    private $templateBlockInfos = array(); // this array stores data about de current block
    private $templateCurrentBlock = null; // this var will store the code for the current block in use
    private $templateBlockLoop = null; // this var defines if the current instance is a block and if it is in loop

    private $templateFunctionRegex = "/(?:\<\_template\:function\.)(.*?)(?:\()(.*?)\)(?:(?: )?(?:\/)?\>)/"; // this regex is used to check if the HTML has any function calls
    private $templateFunctionReplaceRegex = "/(?:\<\_template\:function\.)(?:\\field?)(?:\()?(.*?)(?:\))?(?:(?: )?(?:\/)?\>)/";

    /** Will set the basics needs for the class */
    public function __construct($template = false)
    {
        $this->templateInit = microtime(true);
        $this->templateInitialMemory = memory_get_usage();
        if (is_object($template)) {
            $this->setDir($template->getDir());
            foreach ($template->getVars() as $var => $value) {
                $this->addVar($var, $value);
            }
        }
        if (is_array($template)) {
            if (isset($template['dir'])) {
                $this->setDir($template['dir']);
            }
            if (isset($template['vars'])) {
                foreach ($template['vars'] as $var => $value) {
                    $this->addVar($var, $value);
                }
            }
            if (isset($template['blockInfo'])) {
                $this->templateBlockInfos = $template['blockInfo'];
            }
            if (isset($template['file'])) {
                $this->loadFile($template['file'], (isset($template['raw']) && $template['raw'] ? true : false));
            }
            if (isset($template['html'])) {
                $this->HTML = $template['html'];
            }
        }
        return $this;
    }

    /** This sets the directory where are the template layout/part files */
    public function setDir($dir)
    {
        $dir = preg_replace("/\/{1,}|\\{1,}/", DIRECTORY_SEPARATOR, $dir);
        $lastChar = substr($dir, -1);
        if ($lastChar !== DIRECTORY_SEPARATOR) {
            $this->templateDir = $dir . DIRECTORY_SEPARATOR;
            unset($lastChar);
        } else {
            $this->templateDir = $dir;
        }
        return $this;
    }

    /** This will add a $var to $this->templateVars */
    private function addVar($var, $value)
    {
        $this->templateVars[$var] = $value;
        return $this;
    }

    /** This will "replace" the var code on the template layout to the right value */
    public function setVar($data)
    {
        foreach ($data as $field => $value) {
            $regex = str_replace("\\field", addSlashes($field), $this->templateVarRegex);
            $this->HTML = preg_replace($regex, $value, $this->HTML);
        }
        unset($regex);
        $this->prepareDocument();
        return $this;
    }

    /** Returns the current object dir */
    private function getDir()
    {
        return $this->templateDir;
    }

    /** Return the template vars */
    public function getVars()
    {
        return $this->templateVars;
    }

    /** This will remove all template var regex ocurrences */
    private function clearVars()
    {
        $regex = str_replace("\\field", "[a-zA-Z0-9]{1,}", $this->templateVarRegex);
        $this->HTML = preg_replace($regex, "", $this->HTML);
        return $this;
    }


    /** This method removes extra white spaces and line breaks on the HTML */
    private function prepareDocument()
    {
        $this->HTML = preg_replace($this->templatePrepareDocumentRegex, "", $this->HTML);
        $this->prepareIfs()->prepareBlocks();
        return $this;
    }

    /** This will insert a HTML comment on every template if statement - replacing the <_template></_template> tags and using the comment instead of tags */
    private function prepareIfs()
    {
        if (preg_match_all($this->templateIfRegex, $this->HTML, $matches)) {
            $matchedConditions = $matches[1];
            foreach ($matchedConditions as $condition) {
                $condition = str_replace("$", "", $condition);
                $condition = explode(":", $condition);
                $code = md5($condition[0]);
                $this->templateIfs[$code]['condition'] = array("condition" => $condition[0], "status" => false);
                if (isset($condition[1])) {
                    if (!array_key_exists($code, $this->templateIfsKeys)) {
                        $this->templateIfsKeys[$code] = array("condition" => $condition[0], "childConditions" => array());
                    } else {
                        if (!is_array($this->templateIfsKeys[$code])) {
                            $this->templateIfsKeys[$code] = array("condition" => $condition[0], "childConditions" => array());
                        }
                    }
                    $childCode = md5($condition[1]);
                    $this->templateIfs[$code]['childConditions'][$childCode] = array("condition" => $condition[1], "status" => false);
                    if (!array_key_exists($childCode, $this->templateIfsKeys[$code]["childConditions"])) {
                        $this->templateIfsKeys[$code]["childConditions"][$childCode] = $condition[1];
                    }
                } else {
                    if (!array_key_exists($code, $this->templateIfsKeys)) {
                        $this->templateIfsKeys[$code] = $condition[0];
                    }
                }
            }
            $reverse = array_reverse($this->templateIfsKeys);
            foreach ($reverse as $code => $condition) {
                if (isset($condition['condition'])) {
                    $condition['childConditions'] = array_reverse($condition['childConditions']);
                    foreach ($condition['childConditions'] as $childCode => $childCondition) {
                        $comment = "<!-- $condition[condition]:$childCondition - $code:$childCode -->";
                        $regex = str_replace("{condition}", "(?:\\$)?" . addslashes($condition['condition']) . "\:" . "(?:\\$)?" . addslashes($childCondition), $this->templatePrepareIfRegex);
                        $replace = $comment . "\\1" . $comment;
                        $this->HTML = preg_replace($regex, $replace, $this->HTML);
                    }
                    $comment = "<!-- " . $condition["condition"] . " - $code -->";
                    $regex = str_replace("{condition}", "(?:\\$)?" . addslashes($condition["condition"]), $this->templatePrepareIfRegex);
                    $replace = $comment . "\\1" . $comment;
                    $this->HTML = preg_replace($regex, $replace, $this->HTML);
                } else {
                    $comment = "<!-- $condition - $code -->";
                    $regex = str_replace("{condition}", "(?:\\$)?" . addslashes($condition), $this->templatePrepareIfRegex);
                    $replace = $comment . "\\1" . $comment;
                    $this->HTML = preg_replace($regex, $replace, $this->HTML);
                }
            }
            unset($reverse, $replace, $regex, $comment, $condition, $matchedConditions, $matches);
        };
        return $this;
    }

    /** This method sets if a condition is true or false */
    public function setIf($condition, $bool = false)
    {
        $condition = explode(":", str_replace("$", "", $condition));
        $code = md5($condition[0]);
        if (isset($this->templateIfs[$code])) {
            if (isset($condition[1])) {
                $childCode = md5($condition[1]);
                if (isset($this->templateIfs[$code]['childConditions'][$childCode])) {
                    $this->templateIfs[$code]['childConditions'][$childCode]['status'] = $bool;
                }
                $true = 0;
                foreach ($this->templateIfs[$code]['childConditions'] as $childCode => $conditions) {
                    if ($conditions["status"]) {
                        $true++;
                    }
                }
                if ($true > 0) {
                    $this->templateIfs[$code]['condition']['status'] = true;
                }
            } else {
                $this->templateIfs[$code]['condition']['status'] = $bool;
            }
        }
        unset($code, $condition, $childCode);
        return $this;
    }

    /** This method removes the "if HTML comments" that the template inserted on the page */
    private function clearIfs()
    {
        $this->prepareIfs();
        foreach ($this->templateIfsKeys as $code => $condition) {
            if (is_string($condition)) {
                if (!isset($this->templateIfs[$code]['condition']['status']) || !$this->templateIfs[$code]['condition']['status']) {
                    $comment = addslashes("<!-- $condition - $code -->");
                    $this->HTML = preg_replace("/" . $comment . ".*?" . $comment . "/", "", $this->HTML);
                } else {
                    $comment = addslashes("<!-- $condition - $code -->");
                    $this->HTML = preg_replace("/" . addslashes($comment) . "/", "", $this->HTML);
                }
            } else {
                foreach ($condition['childConditions'] as $childCode => $childCondition) {
                    if (!$this->templateIfs[$code]['childConditions'][$childCode]['status'] || !$this->templateIfs[$code]['condition']['status']) {
                        $comment = "<!-- $condition[condition]:$childCondition - $code:$childCode -->";
                        $this->HTML = preg_replace("/" . addslashes($comment) . "(.*?)" . addslashes($comment) . "/", "", $this->HTML);
                    } else {
                        $comment = "<!-- $condition[condition]:$childCondition - $code:$childCode -->";
                        $this->HTML = preg_replace("/" . addslashes($comment) . "/", "", $this->HTML);
                    }
                }
                if (!isset($this->templateIfs[$code]['condition']['status']) || !$this->templateIfs[$code]['condition']['status']) {
                    $comment = addslashes("<!-- " . $condition["condition"] . " - $code -->");
                    $this->HTML = preg_replace("/" . $comment . ".*?" . $comment . "/", "", $this->HTML);
                }
            }
        }
        return $this;
    }

    /** This will insert a HTML comment on every template block statement - replacing the <_template></_template> tags and using the comment instead of tags */
    private function prepareBlocks()
    {
        if (preg_match_all($this->templateBlockRegex, $this->HTML, $matches)) {
            $matchedBlocks = array_reverse($matches[1]);
            foreach ($matchedBlocks as $key => $block) {
                $block = str_replace("$", "", $block);
                $code = md5($block);
                $comment = "<!-- block:$block - $code -->";
                $regex = str_replace("{condition}", addslashes($block), $this->templatePrepareBlockRegex);
                $replace = $comment . "\\1" . $comment;
                $this->HTML = preg_replace($regex, $replace, $this->HTML);
                $this->templateBlocksKeys[$code] = $block;
            }
            unset($matchedBlocks, $block, $code, $comment, $regex, $replace);
        }
        return $this;
    }

    /** This method returns a new Template instance, with the block HTML */
    public function getBlock($name)
    {
        $name = str_replace("$", "", $name);
        $code = md5($name);
        $this->prepareDocument();
        if (isset($this->templateBlocksKeys[$code])) {
            $comment = "<!-- block:$name - $code -->";
            $regex = "/(?:" . $comment . ")(.*?)(?:" . $comment . ")/";
            if (preg_match($regex, $this->HTML, $matches)) {
                $class = get_class($this);
                $block = new $class(array("html" => $matches[1], "dir" => $this->getDir(), "vars" => $this->getVars(), "blockInfo" => array("name" => $name, "code" => $code)));
                $block->templateIfs = $this->templateIfs;
                $block->templateIfsKeys = $this->templateIfsKeys;
                return $block;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /** This method will set the block on the HTML */
    public function setBlock($block = false)
    {
        if ($block instanceof $this) {
            $blockInfo = $block->templateBlockInfos;
            $comment = addslashes("<!-- block:$blockInfo[name] - $blockInfo[code] -->");
            $regex = "/($comment).*?($comment)/";
            $block->unsetBlockLoop();
            $this->templateIfsKeys = array_merge($this->templateIfsKeys, $block->templateIfsKeys);
            $this->templateIfs = array_merge($this->templateIfs, $block->templateIfs);
            if (isset($block->templateCurrentBlock['rendered']) && is_array($block->templateCurrentBlock['rendered'])) {
                $this->HTML = preg_replace($regex, "\\1" . implode("", $block->templateCurrentBlock['rendered']) . "\\2", $this->HTML);
            } else if (isset($block->templateCurrentBlock['rendered']) && is_string($block->templateCurrentBlock['rendered'])) {
                $this->HTML = preg_replace($regex, "\\1" . $block->templateCurrentBlock['rendered'] . "\\2", $this->HTML);
            } else {
                $this->HTML = preg_replace($regex, "\\1" . $block->rawRender() . "\\2", $this->HTML);
            }
            $this->prepareDocument();
            unset($block);
            return $this;
        }
        if (is_array($block)) {
            foreach ($block as $key => $value) {
                $this->addVar($key, $value);
            }
            $class = get_class($this);
            $new = new $class(array("vars" => $this->getVars(), "dir" => $this->getDir(), "html" => $this->rawRender()));
            $new->templateIfsKeys = $this->templateIfsKeys;
            $new->templateIfs = $this->templateIfs;
            if ($this->templateBlockLoop) {
                $this->templateCurrentBlock['rendered'][] = $new->register()->rawRender();
            } else {
                $this->templateCurrentBlock['rendered'] = $new->register()->rawRender();
            }
            return $this;
        }
    }

    /** This method removes the given block from the document */
    public function unsetBlock($name)
    {
        $name = str_replace("$", "", $name);
        $code = md5($name);
        if (isset($this->templateBlocksKeys[$code])) {
            $comment = "<!-- block:$name - $code -->";
            $regex = "/(?:" . $comment . ")(.*?)(?:" . $comment . ")/";
            $this->HTML = preg_replace($regex, "", $this->HTML);
        }
        return $this;
    }

    /** This will set the current block on loop */
    public function setBlockLoop()
    {
        $this->templateBlockLoop = true;
        $this->templateCurrentBlock["HTML"] = $this->HTML;
        return $this;
    }

    /** This will set the current block on loop */
    public function unsetBlockLoop()
    {
        $this->templateBlockLoop = false;
        return $this;
    }

    /** This will remove the blocks HTML comments from the string */
    private function clearBlocks()
    {
        $this->prepareBlocks();
        foreach ($this->templateBlocksKeys as $code => $block) {
            $comment = addslashes("<!-- block:$block - $code -->");
            $regex = "/" . $comment . "/";
            $this->HTML = preg_replace($regex, "", $this->HTML);
        }
        return $this;
    }

    /** This method will apply the function result on the HTML */
    private function setFunction()
    {
        if (preg_match_all($this->templateFunctionRegex, $this->HTML, $matches)) {
            unset($matches[0]);
            $functions = $matches[1];
            $arguments = $matches[2];
            foreach ($functions as $key => $function) {
                $function = trim($function);
                $argument = trim($arguments[$key]);
                if ($argument !== "") {
                    $isVar = function ($argument) {
                        $regex = str_replace("\\field", addSlashes($argument), $this->templateVarRegex);
                        if (preg_match($regex, $argument, $matches) || array_key_exists($argument, $this->templateVars)) {
                            if (isset($matches[1])) {
                                return $matches[1];
                            }
                            if (isset($this->templateVars[$argument])) {
                                return $this->templateVars[$argument];
                            }
                        }
                        return $argument;
                    };
                }
                try {
                    $regex = str_replace("\\field", $function, $this->templateFunctionReplaceRegex);
                    if (function_exists($function)) {
                        if ($argument !== "") {
                            $var = $isVar($argument);
                            $result = call_user_func($function, $var);
                        } else {
                            $result = call_user_func($function);
                        }
                        if ($result) {
                            //$this->HTML = preg_replace($this->templateFunctionReplaceRegex, "\\1", $this->HTML);
                            $this->HTML = preg_replace($regex, $result, $this->HTML);
                            if (isset($var)) {
                                $this->setVar(array($var => $result));
                            }
                        } else {
                            $this->HTML = preg_replace($regex, "<!-- the function `$function` didn't had any result -->", $this->HTML);
                        }
                    } else {
                        $function = addSlashes(str_replace(array("<", ">"), array("&lt;", "&gt;"), $function));
                        $this->HTML = preg_replace($regex, "<!-- the function `$function` doesn't exists -->", $this->HTML);
                    }
                } catch (Exception $error) {
                    return $error;
                }
            }
        }
    }

    /** This method concatenates code on the current template instance */
    public function code($code)
    {
        $this->HTML .= $code;
        $this->prepareDocument();
        return $this;
    }

    /** This method gets the contents of the given file. If $raw == true then it will return the raw HTML code */
    public function loadFile($filePath = false, $raw = false)
    {
        if ($filePath) {
            $filePath = preg_replace("/\/{1,}|\\{1,}/", DIRECTORY_SEPARATOR, $filePath);
            $instance = $this;
            if ($instance->templateDir == "") {
                $instance->templateDir = './';
            }
            $path = $instance->templateDir . $filePath;
            if (file_exists($path)) {
                $instance->HTML = file_get_contents($path);
                if (preg_match_all($this->templateIncludeRegex, $instance->HTML, $matches)) {
                    $matches = $matches[1];
                    $class = get_class($instance);
                    foreach ($matches as $match) {
                        $fileName = preg_replace("/\/{1,}|\\{1,}/", DIRECTORY_SEPARATOR, $match);
                        $data = array(
                            "dir" => $this->getDir(),
                            "file" => $fileName,
                            "raw" => true,
                        );
                        $childNode = new $class($data);
                        unset($data);
                        $fileName = explode(DIRECTORY_SEPARATOR, $fileName);
                        $fileName = $fileName[count($fileName) - 1];
                        $instance->HTML = preg_replace("/\<\_template\:loadFile\(.*?" . addslashes($fileName) . "\)( )?(\/)?\/>/", $childNode->rawRender(), $instance->HTML);
                        unset($childNode, $aux, $fileName);
                    }
                }
                if ($raw) {
                    return $instance->rawRender();
                }
            }
            $this->prepareDocument();
            return $instance;
        } else {
            $instance = $this;
            if (preg_match($this->templateIncludeRegex, $instance->HTML, $matches)) {
                $fileName = implode(DIRECTORY_SEPARATOR, explode("|", preg_replace("/\/{1,}|\\{1,}/", "|", $matches[1])));
                $class = get_class($instance);
                $data = array(
                    "dir" => $this->getDir(),
                    "file" => $fileName,
                    "raw" => true,
                );
                $childNode = new $class($data);
                unset($data);
                $fileName = explode(DIRECTORY_SEPARATOR, $fileName);
                $fileName = $fileName[count($fileName) - 1];
                $instance->HTML = preg_replace("/\<\_template\:loadFile\(.*?" . addslashes($fileName) . "\)( )?(\/)?\/>/", $childNode->rawRender(), $instance->HTML);
                unset($childNode, $aux, $fileName);
            }
            if ($raw) {
                return $instance->rawRender();
            }
            $instance->prepareDocument();
            return $instance;
        }
    }

    /** This method removes all template HTML tags on the document */
    private function clear()
    {
        $this->clearVars()->clearIfs()->clearBlocks();
        return $this;
    }

    /** This method sets all template vars on the document */
    public function register()
    {
        $this->setVar($this->templateVars)->setFunction();
        return $this;
    }

    /** This method returns the HTML without any render by the template side */
    public function rawRender($echo = false)
    {
        if ($echo) {
            echo $this->HTML;
        } else {
            return $this->HTML;
        }
    }

    /** This method returns the HTML with the template rendering */
    public function render($echo = false)
    {
        $this->register()->clear();
        $this->HTML .= "\n<!-- \n Template Class Data\n Rendered: " . (microtime(true) - $this->templateInit) . " seconds;\n Initial Memory Use: " . $this->templateInitialMemory . ";\n Memory Peak: " . memory_get_peak_usage() . "\n -->";
        if ($echo) {
            echo $this->HTML;
        }
        return $this->HTML;
    }
}
