<?php

defined('MOBICMS') or die('Error: restricted access');

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var Mobicms\Api\ToolsInterface $tools */
$tools = $container->get(Mobicms\Api\ToolsInterface::class);

if (empty($_GET['n'])) {
    require('../system/head.php');
    echo $tools->displayError(_t('Wrong data'));
    require('../system/end.php');
    exit;
}

$n = trim($_GET['n']);
$o = opendir("../files/forum/topics");

while ($f = readdir($o)) {
    if ($f != "." && $f != ".." && $f != "index.php" && $f != ".htaccess") {
        $ff = pathinfo($f, PATHINFO_EXTENSION);
        $f1 = str_replace(".$ff", "", $f);
        $a[] = $f;
        $b[] = $f1;
    }
}

$tt = count($a);

if (!in_array($n, $b)) {
    require_once('../system/head.php');
    echo $tools->displayError(_t('Wrong data'));
    require_once('../system/end.php');
    exit;
}

for ($i = 0; $i < $tt; $i++) {
    $tf = pathinfo($a[$i], PATHINFO_EXTENSION);
    $tf1 = str_replace(".$tf", "", $a[$i]);
    if ($n == $tf1) {
        header("Location: ../files/forum/topics/$n.$tf");
    }
}
