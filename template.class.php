<?php

/** Template class (work in progress) */

class Template
{
    private $templateIncludeRegex = "/(?:\<\_template\:loadFile\()([a-zA-Z0-9]{1,}\.html)(?:\)(?: )?(?:\/)?\/>)/"; // this regex loads other child template files inside a template files - it acts recursively
    private $templateVarRegex = "/\<\_template\:(\\$)?\\field( )?(\/)?\>|\{\{(:?\\$)?\\field\}\}/"; // \\field will will be replaced on the setVar function

    //<_template:if($condition:apelido)>
    private $templateIfRegex = "/(?:\<\_template\:if\()((?:(?:\\$)?[a-zA-Z0-9]+)(?:(?:\:)(?:\\$)?(?:[a-zA-Z0-9]+))?)(?:\)(?: )?(?:\/)?\>)/";
    private $templateSetIfRegex = "/(\<\_template\:if\()/";

    private $templateDir = ""; // this is the directory where the template files are stored
    private $templateVars = array(); // array with defined vars to be set on the template. To define a var you can use: <_template:$var/> <_template:var/> or {{$var}} {{var}}
    private $templateReservedWords = array(
        "if",
        "block",
        "loadFile",
    );

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
            $this->loadFile($template['file']);
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
            //if (!in_array($field, $this->templateReservedWords)) {
            $this->HTML = preg_replace($regex, $value, $this->HTML);
            //}
        }
        return $this;
    }

    /** This will add a $var to $this->templateVars */
    private function addVar($var, $value)
    {
        $this->templateVars[$var] = $value;
    }


    private function getIfs()
    {
        if (preg_match_all($this->templateIfRegex, $this->HTML, $matches)) {
            $matchedConditions = $matches[1];
            foreach ($matchedConditions as $key => $condition) {
                $condition = explode(":", $condition);
                $code = md5($condition[0]);
                if (isset($condition[1])) {
                    if (!array_key_exists($code, $this->templateIfsKeys)) {
                        $this->templateIfsKeys[$code] = array("condition" => $condition[0], "childConditions" => array());
                        echo "<pre>";
                        print_r($this->templateIfsKeys);
                        echo "</pre>";
                    } else {
                        if (!is_array($this->templateIfsKeys[$code])) {
                            $this->templateIfsKeys[$code] = array("condition" => $condition[0], "childConditions" => array());
                        }
                    }
                    $childCode = md5($condition[1]);
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
            foreach ($reverse as $key => $condition) {
                if (isset($condition['condition'])) {
                } else {
                }
            }
        };
    }

    private function setIf()
    {
        if (!empty($this->ifs)) {
            foreach ($this->ifs as $if => $value) {
                if (!$value) {
                    $this->HTML = preg_replace("/\<if\:" . addslashes($if) . "\>(.*?)\<\/if\:" . addslashes($if) . "\>/s", "", $this->HTML);
                } else {
                    $this->HTML = preg_replace("/\<if\:" . addslashes($if) . "\>(.*?)\<\/if\:" . addslashes($if) . "\>/s", "$1", $this->HTML);
                }
            }
        }
    }

    private function getIf($condicao)
    {
        if (array_key_exists($condicao, $this->ifs)) {
            $this->ifs[$condicao] = true;
        }
    }

    /** Returns the current object dir */
    private function getDir()
    {
        return $this->templateDir;
    }

    private function getVars()
    {
        return $this->templateVars;
    }

    private function getBlock($name)
    {
    }

    /** This method gets the contents of the given file. If $raw == true then it will return the rawRender() code */
    public function loadFile($filePath, $raw = false)
    {
        if ($this->templateDir !== "") {
            $path = $this->templateDir . $filePath;
            if (file_exists($path)) {
                $this->HTML = preg_replace("/\r|\n/", "", file_get_contents($path));
                if (preg_match($this->templateIncludeRegex, $this->HTML, $matches)) {
                    $fileName = $matches[1];
                    $class = get_class($this);
                    $childNode = new $class(array("dir" => $this->getDir(), "file" => $fileName));
                    $this->HTML = preg_replace("/\<\_template\:loadFile\(" . addslashes($fileName) . "\)( )?(\/)?\/>/", $childNode->rawRender(), $this->HTML);
                    unset($childNode);
                }
                if ($raw) {
                    return $this->rawRender();
                }
            }
            $this->getIfs();
            return $this;
        } else {
            return false;
        }
    }

    private function register()
    {
        $this->setIf();
        $this->setVar($this->templateVars);
        $this->rendered = $this->HTML;
        return $this;
    }

    private function clear()
    {
        $this->rendered = preg_replace("/\{\{[0-9a-zA-Z]{1,}\}\}/", "", $this->rendered);
        $this->rendered = preg_replace('/!\s+!/', ' ', $this->rendered);

        return $this;
    }

    public function rawRender()
    {
        return $this->HTML;
    }

    public function render()
    {
        $this->register();
        $this->clear();
        return $this->rendered;
    }
}
