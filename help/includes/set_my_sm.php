<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

defined('MOBICMS') or die('Error: restricted access');

// Настраиваем список своих смайлов
$adm = isset($_GET['adm']);
$add = isset($_POST['add']);
$delete = isset($_POST['delete']);
$cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var Mobicms\Api\UserInterface $systemUser */
$systemUser = $container->get(Mobicms\Api\UserInterface::class);

/** @var Mobicms\Api\ToolsInterface $tools */
$tools = $container->get(Mobicms\Api\ToolsInterface::class);
$start = $tools->getPgStart();

if (($adm && !$systemUser->rights) || ($add && !$adm && !$cat) || ($delete && !$_POST['delete_sm']) || ($add && !$_POST['add_sm'])) {
    echo $tools->displayError(_t('Wrong data'), '<a href="faq.php?act=smileys">' . _t('Smilies') . '</a>');
    require('../system/end.php');
    exit;
}

$smileys = unserialize($systemUser->smileys);

if (!is_array($smileys)) {
    $smileys = [];
}

if ($delete) {
    $smileys = array_diff($smileys, $_POST['delete_sm']);
}

if ($add) {
    $add_sm = $_POST['add_sm'];
    $smileys = array_unique(array_merge($smileys, $add_sm));
}

if (count($smileys) > $user_smileys) {
    $smileys = array_chunk($smileys, $user_smileys, true);
    $smileys = $smileys[0];
}

$db->query("UPDATE `users` SET `smileys` = " . $db->quote(serialize($smileys)) . " WHERE `id` = " . $systemUser->id);

if ($delete || isset($_GET['clean'])) {
    header('location: index.php?act=my_smilies&start=' . $start . '');
} else {
    header('location: index.php?act=' . ($adm ? 'admsmilies' : 'usersmilies&cat=' . urlencode($cat) . '') . '&start=' . $start . '');
}
