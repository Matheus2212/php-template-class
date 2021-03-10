<?php

/** Template class (work in progress) */

class Template
{
    private $templateDir = "";
    private $HTML = "";
    private $rendered = "";
    private $ifs = array();
    private $block = "";
    private $blockLoop = "";
    private $safeVars = array();

    public function __construct($template = false)
    {
        if (is_object($template)) {
            $this->templateDir = $template->getDir();
            $this->safeVars = $template->getSafeVars();
            return $this;
        }
    }

    public function setTemplateDir($dir)
    {
        $lastChar = substr($dir, -1);
        if ($lastChar !== DIRECTORY_SEPARATOR) {
            $this->templateDir = $dir . DIRECTORY_SEPARATOR;
            unset($lastChar);
        } else {
            $this->templateDir = $dir;
        }
    }

    public function loadFile($filePath, $simple = false)
    {
        if ($this->templateDir !== "") {
            $path = $this->templateDir . $filePath;
            if (file_exists($path)) {
                $this->HTML = preg_replace("/\r|\n/", "", file_get_contents($path));
                if (preg_match("/\<if\:/", $this->HTML)) {
                    if (preg_match_all("/(?:\<if\:)([a-zA-Z0-9_]+)(?:\>)/", $this->HTML, $matches)) {
                        unset($matches[0]);
                        if (isset($matches[1])) {
                            foreach ($matches[1] as $if) {
                                $this->ifs[$if] = false;
                            }
                        }
                    }
                }
                if ($simple) {
                    return $this->render();
                }
            }
            return $this;
        } else {
            return false;
        }
    }

    public function set($data)
    {
        foreach ($data as $field => $value) {
            $this->HTML = str_replace("{{" . $field . "}}", $value, $this->HTML);
            if (array_key_exists($field, $this->ifs)) {
                $this->ifs[$field] = $value;
            }
        }
        return $this;
    }

    public function addSafeVar($var, $value)
    {
        $this->safeVars[$var] = $value;
    }

    public function getIf($condicao)
    {
        if (array_key_exists($condicao, $this->ifs)) {
            $this->ifs[$condicao] = true;
        }
    }

    public function getDir()
    {
        return $this->templateDir;
    }

    public function getSafeVars()
    {
        return $this->safeVars;
    }

    public function setIf()
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

    public function block($name)
    {
    }

    public function register()
    {
        $this->setIf();
        $this->set($this->safeVars);
        $this->rendered = $this->HTML;
        return $this;
    }

    public function clear()
    {
        $this->rendered = preg_replace("/\{\{[0-9a-zA-Z]{1,}\}\}/", "", $this->rendered);
        return $this;
    }

    public function render()
    {
        $this->register();
        $this->clear();
        return $this->rendered;
    }
}
