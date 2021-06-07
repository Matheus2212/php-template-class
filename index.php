<?php

header("Content-type:text/html");
include("template.class.php");

$variaveis = array(
        "titulo" => "Teste com template",
        "body" => "Só quero ver o que acontecerá aqui"
);

$layout = new Template(array("dir" => "html_files", "file" => "index.html", "vars" => $variaveis));

$block = $layout->getBlock("loop");
if ($block) {
        $block->setBlockLoop();
        for ($i = 0; $i < 10; $i++) {
                $block->setBlock(array("i"=>$i,"outro_arquivo"=>"será?"));

        }
        $block->unsetBlockLoop();
}

$layout->render(true);
