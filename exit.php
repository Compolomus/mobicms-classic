<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

define('MOBICMS', 1);

require('system/bootstrap.php');

/** @var Mobicms\Api\ConfigInterface $config */
$config = App::getContainer()->get(Mobicms\Api\ConfigInterface::class);

$referer = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : $config->homeurl;

if (isset($_POST['submit'])) {
    setcookie('cuid', '');
    setcookie('cups', '');
    session_destroy();
    header('Location: ' . $config->homeurl);
} else {
    require('system/head.php');
    echo '<div class="rmenu">' .
        '<p>' . _t('Are you sure you want to leave the site?', 'system') . '</p>' .
        '<form action="exit.php" method="post"><p><input type="submit" name="submit" value="' . _t('Logout', 'system') . '" /></p></form>' .
        '<p><a href="' . $referer . '">' . _t('Cancel', 'system') . '</a></p>' .
        '</div>';
    require('system/end.php');
}
