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

/**
 * @var int                       $id
 *
 * @var PDO                       $db
 * @var Mobicms\Api\UserInterface $systemUser
 */

if ($systemUser->isValid()) {
    $topic = $db->query("SELECT COUNT(*) FROM `forum` WHERE `type`='t' AND `id` = '$id' AND `edit` != '1'")->fetchColumn();
    $vote = abs(intval($_POST['vote']));
    $topic_vote = $db->query("SELECT COUNT(*) FROM `cms_forum_vote` WHERE `type` = '2' AND `id` = '$vote' AND `topic` = '$id'")->fetchColumn();
    $vote_user = $db->query("SELECT COUNT(*) FROM `cms_forum_vote_users` WHERE `user` = '" . $systemUser->id . "' AND `topic` = '$id'")->fetchColumn();

    ob_start();

    if ($topic_vote == 0 || $vote_user > 0 || $topic == 0) {
        exit(_t('Wrong data'));
    }

    $db->exec("INSERT INTO `cms_forum_vote_users` SET `topic` = '$id', `user` = '" . $systemUser->id . "', `vote` = '$vote'");
    $db->exec("UPDATE `cms_forum_vote` SET `count` = count + 1 WHERE id = '$vote'");
    $db->exec("UPDATE `cms_forum_vote` SET `count` = count + 1 WHERE topic = '$id' AND `type` = '1'");
    echo _t('Vote accepted') . '<br /><a href="' . htmlspecialchars(getenv("HTTP_REFERER")) . '">' . _t('Back') . '</a>';
}
