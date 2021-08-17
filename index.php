<?php

// verificar IF

header("Content-type:text/html");
require "template.class.php";

$vars = array(
        "title" => "php template engine",
);

$layout = new Template();
$layout->setDir("./html_files");
$layout->loadFile("index.html");
//$layout->setVar($vars);
$layout->setIf("hasVar", true);
$block = $layout->getBlock("childBlock");
if ($block) {
        $block->setBlockLoop();
        for ($i = 0; $i < 8; $i++) {
                $block->setBlock(array("i" => $i));
        }
        $layout->setBlock($block);
}

$layout->render(true);
