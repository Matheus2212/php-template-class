<?php

// verificar IF

header("Content-type:text/html");
require "TemplateClass.php";

$vars = array(
        "title" => "PHP Template Engine",
        "cor" => "#f52342",
       "meu_cabelo_eh_vermelho" => false
);

$layout = new Template();
$layout->setDir("./html_files");
$layout->loadFile("index.html");
$layout->setVar($vars);
$layout->render(true);
