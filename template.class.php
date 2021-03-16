<?php

/** 
 * PHP Template class (work in progress) 
 * 
 * CHANGELOG
 * 2021-02-15 -> Class created
 * 2021-03-15 -> Defined vars definition, loadFile function, ifs statements
 * 2021-03-16 -> Aplied the ifs and loadfiles replacements.
 * */

class Template
{
    private $templateIncludeRegex = "/(?:\<\_template\:loadFile\()([a-zA-Z0-9]{1,}\.html)(?:\)(?: )?(?:\/)?\/>)/"; // this regex loads other child template files inside a template files - it acts recursively
    private $templateVarRegex = "/\<\_template\:(\\$)?\\field( )?(\/)?\>|\{\{(:?\\$)?\\field\}\}/"; // \\field will will be replaced on the setVar function

    private $templateIfRegex = "/(?:\<\_template\:if\()((?:(?:\\$)?[a-zA-Z0-9]+)(?:(?:\:)(?:\\$)?(?:[a-zA-Z0-9]+))?)(?:\)(?: )?(?:\/)?\>)/"; // this regex is used to prepare the conditions on the template ifs
    private $templatePrepareIfRegex = "/(?:\<\_template\:if\({condition}\)\>)(.*?)(?:\<\/\_template\:if\>)/"; // this regex also is used to prepare the conditions on the template ifs

    private $templateDir = ""; // this is the directory where the template files are stored
    private $templateVars = array(); // array with defined vars to be set on the template. To define a var you can use: <_template:$var/> <_template:var/> or {{$var}} {{var}}

    private $templateIfs = array(); // array with the "ifs" keys on the template file
    private $templateIfsKeys = array(); // array with the "ifs" keys on the template file

    private $templateBlock = "";
    private $blockLoop = "";

    private $HTML = "";
    private $rendered = "";

    /** Will set the basics needs for the class */
    public function __construct($template = false)
    {
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
        }
        if (isset($template['file'])) {
            $this->loadFile($template['file'], (isset($template['raw']) && $template['raw'] ? true : false));
        }
        return $this;
    }

    /** This sets the directory where are the template layout/part files */
    public function setDir($dir)
    {
        $lastChar = substr($dir, -1);
        if ($lastChar !== DIRECTORY_SEPARATOR) {
            $this->templateDir = $dir . DIRECTORY_SEPARATOR;
            unset($lastChar);
        } else {
            $this->templateDir = $dir;
        }
    }

    /** This will "replace" the var code on the template layout to the right value */
    public function setVar($data)
    {
        foreach ($data as $field => $value) {
            $regex = str_replace("\\field", addSlashes($field), $this->templateVarRegex);
            $this->HTML = preg_replace($regex, $value, $this->HTML);
        }
        unset($regex);
        return $this;
    }

    /** This will add a $var to $this->templateVars */
    private function addVar($var, $value)
    {
        $this->templateVars[$var] = $value;
        return $this;
    }

    /** Returns the current object dir */
    private function getDir()
    {
        return $this->templateDir;
    }

    /** Return the template vars */
    private function getVars()
    {
        return $this->templateVars;
    }

    private function getBlock($name)
    {
    }

    /** This will insert a HTML comment on every template if statement - replacing the <_template></_template> tags and using the comment instead of 'em */
    private function prepareIfs()
    {
        if (preg_match_all($this->templateIfRegex, $this->HTML, $matches)) {
            $matchedConditions = $matches[1];
            foreach ($matchedConditions as $key => $condition) {
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

    /** This function sets if a condition is true or false */
    public function setIf($condition, $bool = false)
    {
        $condition = explode(":", str_replace("$", "", $condition));
        $code = md5($condition[0]);
        if (isset($this->templateIfs[$code]) && $this->templateIfs[$code]['condition']['condition'] == $condition[0]) {
            $this->templateIfs[$code]['condition']['status'] = $bool;
            if (isset($condition[1])) {
                $childCode = md5($condition[1]);
                if (isset($this->templateIfs[$code]['childConditions'][$childCode]) && $this->templateIfs[$code]['childConditions'][$childCode]['condition'] == $condition[1]) {
                    $this->templateIfs[$code]['childConditions'][$childCode]['status'] = $bool;
                }
            }
        }
        unset($code, $condition, $childCode);
        return $this;
    }

    /** This function removes he if HTML comments that the template inserted on the page */
    private function clearIfs()
    {
        foreach ($this->templateIfsKeys as $code => $condition) {
            if (is_string($condition)) {
                if (!$this->templateIfs[$code]['condition']['status']) {
                    $comment = addslashes("<!-- $condition - $code -->");
                    $this->HTML = preg_replace("/" . $comment . ".*?" . $comment . "/", "", $this->HTML);
                }
            } else {
                foreach ($condition['childConditions'] as $childCode => $childCondition) {
                    if (!$this->templateIfs[$code]['childConditions'][$childCode]['status'] || !$this->templateIfs[$code]['condition']['status']) {
                        $comment = "<!-- $condition[condition]:$childCondition - $code:$childCode -->";
                        $this->HTML = preg_replace("/" . addslashes($comment) . "(.*?)" . addslashes($comment) . "/", "", $this->HTML);
                    }
                }
            }
        }
        return $this;
    }

    /** This method gets the contents of the given file. If $raw == true then it will return the raw HTML code */
    public function loadFile($filePath, $raw = false)
    {
        if ($this->templateDir !== "") {
            $path = $this->templateDir . $filePath;
            if (file_exists($path)) {
                $this->HTML = preg_replace("/\r|\n/", "", file_get_contents($path));
                if (preg_match($this->templateIncludeRegex, $this->HTML, $matches)) {
                    $fileName = $matches[1];
                    $class = get_class($this);
                    $childNode = new $class(array("dir" => $this->getDir(), "file" => $fileName, "raw" => true));
                    $this->HTML = preg_replace("/\<\_template\:loadFile\(" . addslashes($fileName) . "\)( )?(\/)?\/>/", $childNode->rawRender(), $this->HTML);
                    unset($childNode);
                }
                if ($raw) {
                    return $this->rawRender();
                }
            }
            $this->prepareIfs();
            return $this;
        } else {
            return false;
        }
    }

    /** This function removes all template HTML tags on the document */
    private function clear()
    {
        $this->clearIfs();
        //$this->rendered = preg_replace("/\{\{[0-9a-zA-Z]{1,}\}\}/", "", $this->rendered);
        $regex = str_replace("\\field", "[a-zA-Z0-9]{1,}", $this->templateVarRegex);
        $this->HTML = preg_replace($regex, "", $this->HTML);
        //$this->rendered = preg_replace('/!\s+!/', ' ', $this->rendered);

        return $this;
    }

    /** This function sets all template vars on the document */
    private function register()
    {
        $this->setVar($this->templateVars);
        return $this;
    }

    /** This function returns the HTML without any render by the template side */
    public function rawRender()
    {
        return $this->HTML;
    }

    /** This function returns the HTML with the template rendering */
    public function render()
    {
        $this->register()->clear();
        return $this->HTML;
    }
}
