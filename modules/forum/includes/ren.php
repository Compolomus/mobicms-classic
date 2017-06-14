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

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Mobicms\Http\Response $response */
$response = $container->get(Mobicms\Http\Response::class);

/** @var Mobicms\Api\UserInterface $systemUser */
$systemUser = $container->get(Mobicms\Api\UserInterface::class);

/** @var Mobicms\Api\ToolsInterface $tools */
$tools = $container->get(Mobicms\Api\ToolsInterface::class);

if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
    if (!$id) {
        require ROOT_PATH . 'system/head.php';
        echo $tools->displayError(_t('Wrong data'));
        require ROOT_PATH . 'system/end.php';
        exit;
    }

    $ms = $db->query("SELECT * FROM `forum` WHERE `id` = '$id'")->fetch();

    if ($ms['type'] != "t") {
        require ROOT_PATH . 'system/head.php';
        echo $tools->displayError(_t('Wrong data'));
        require ROOT_PATH . 'system/end.php';
        exit;
    }

    if (isset($_POST['submit'])) {
        $nn = isset($_POST['nn']) ? trim($_POST['nn']) : '';

        if (!$nn) {
            require ROOT_PATH . 'system/head.php';
            echo $tools->displayError(_t('You have not entered topic name'), '<a href="index.php?act=ren&amp;id=' . $id . '">' . _t('Repeat') . '</a>');
            require ROOT_PATH . 'system/end.php';
            exit;
        }

        // Проверяем, есть ли тема с таким же названием?
        $pt = $db->query("SELECT * FROM `forum` WHERE `type` = 't' AND `refid` = '" . $ms['refid'] . "' and text=" . $db->quote($nn) . " LIMIT 1");

        if ($pt->rowCount()) {
            require ROOT_PATH . 'system/head.php';
            echo $tools->displayError(_t('Topic with same name already exists in this section'), '<a href="index.php?act=ren&amp;id=' . $id . '">' . _t('Repeat') . '</a>');
            require ROOT_PATH . 'system/end.php';
            exit;
        }

        $db->exec("UPDATE `forum` SET  text=" . $db->quote($nn) . " WHERE id='" . $id . "'");

        $response->redirect('?id=' . $id)->sendHeaders();
    } else {
        // Переименовываем тему
        require ROOT_PATH . 'system/head.php';
        echo '<div class="phdr"><a href="index.php?id=' . $id . '"><b>' . _t('Forum') . '</b></a> | ' . _t('Rename Topic') . '</div>' .
            '<div class="menu"><form action="index.php?act=ren&amp;id=' . $id . '" method="post">' .
            '<p><h3>' . _t('Topic name') . '</h3>' .
            '<input type="text" name="nn" value="' . $ms['text'] . '"/></p>' .
            '<p><input type="submit" name="submit" value="' . _t('Save') . '"/></p>' .
            '</form></div>' .
            '<div class="phdr"><a href="index.php?id=' . $id . '">' . _t('Back') . '</a></div>';
    }
}
