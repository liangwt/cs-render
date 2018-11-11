<?php
include "./CsRender.php";
$params = [
    "title"      => "New PHP Render Based On DOM Parse",
    "is_author"  => true,
    "show_intro" => false,
    "intro"      => "no intor",
    "author"     => "liangwt",
    "userInfo"   => [
        "name"   =>"liangwt",
        "sex"    => "m",
        "age"    => 18,
    ],
    "content"    => "This is a php templete rander based on DOM element, which offered a grammar like Vue",
    "items"      => [
        "A",
        "B",
        "C",
    ],
];

$cs = new CsRender();
echo $cs->render("./tpl.html", $params);