<?php

header("Content-type:text/html");
include("template.class.php");

$variaveis = array(
        "title" => "Meu Título",
        "body_var" => "Meu body",
        "nav" => "Menu",
        "footer_var" => "Footer"
);

$layout = new Template(array("dir" => "html_files", "file" => "index.html", "vars" => $variaveis));

$block = $layout->getBlock("loop");

if ($block) {
        
        $block->loadFile("block.html")->setBlockLoop();
        for ($i = 0; $i < 10; $i++) {
                $block->setBlock(array("i" => $i));
                $childBlock = $block->getBlock("childBlock");
                if($childBlock){
                        $childBlock->code("My new code here");
                }
                $block->setBlock($childBlock);
        }
        $layout->setBlock($block);
}

$layout->render(true);
