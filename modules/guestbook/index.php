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

// Сюда можно (через запятую) добавить ID тех юзеров, кто не в администрации,
// но которым разрешено читать и писать в Админ клубе
$guestAccess = [];

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var Mobicms\Api\BbcodeInterface $bbcode */
$bbcode = $container->get(Mobicms\Api\BbcodeInterface::class);

/** @var Mobicms\Api\ConfigInterface $config */
$config = $container->get(Mobicms\Api\ConfigInterface::class);

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = $container->get(Psr\Http\Message\ServerRequestInterface::class);
$queryParams = $request->getQueryParams();
$postParams = $request->getParsedBody();

/** @var Mobicms\Api\UserInterface $systemUser */
$systemUser = $container->get(Mobicms\Api\UserInterface::class);

/** @var Mobicms\Api\ToolsInterface $tools */
$tools = $container->get(Mobicms\Api\ToolsInterface::class);

/** @var Mobicms\Checkpoint\UserConfig $userConfig */
$userConfig = $systemUser->getConfig();

/** @var Zend\I18n\Translator\Translator $translator */
$translator = $container->get(Zend\I18n\Translator\Translator::class);
$translator->addTranslationFilePattern('gettext', __DIR__ . '/locale', '/%s/default.mo');

/** @var League\Plates\Engine $view */
$view = $container->get(League\Plates\Engine::class);

// TODO: тут замутить подключение фабрики
use Compolomus\LSQLQueryBuilder\Builder;
$builder = new Builder;

$id = isset($_REQUEST['id']) ? abs(intval($_REQUEST['id'])) : 0;
$act = isset($queryParams['act']) ? trim($queryParams['act']) : '';

if (isset($_SESSION['ref'])) {
    unset($_SESSION['ref']);
}

// Проверяем права доступа в Админ-Клуб
if (isset($_SESSION['ga']) && $systemUser->rights < 1 && !in_array($systemUser->id, $guestAccess)) {
    unset($_SESSION['ga']);
}

// Задаем заголовки страницы
$pageTitle = isset($_SESSION['ga']) ? _t('Admin Club') : _t('Guestbook');
ob_start();

// Если гостевая закрыта, выводим сообщение и закрываем доступ (кроме Админов)
if (!$config->mod_guest && $systemUser->rights < 7) {
    echo $view->render('system::app/legacy', [
        'title'   => _t('Guestbook'),
        'content' => $tools->displayError(_t('Guestbook is closed')),
    ]);
    exit;
}

switch ($act) {
    case 'delpost':
        // Удаление отдельного поста
        if ($systemUser->rights >= 6 && $id) {
            if (isset($queryParams['yes'])) {
                $db->prepare($builder->setTable('guest')->delete($id))
                    ->execute($builder->placeholders());
                header('Location: ?');
            } else {
                echo '<div class="phdr"><a href="index.php"><b>' . _t('Guestbook') . '</b></a> | ' . _t('Delete message') . '</div>' .
                    '<div class="rmenu"><p>' . _t('Do you really want to delete?') . '?<br>' .
                    '<a href="index.php?act=delpost&amp;id=' . $id . '&amp;yes">' . _t('Delete') . '</a> | ' .
                    '<a href="index.php">' . _t('Cancel') . '</a></p></div>';
            }
        }
        break;

    case 'say':
        // Добавление нового поста
        $admset = isset($_SESSION['ga']) ? 1 : 0; // Задаем куда вставляем, в Админ клуб (1), или в Гастивуху (0)
        // Принимаем и обрабатываем данные
        $name = isset($postParams['name']) ? mb_substr(trim($postParams['name']), 0, 20) : '';
        $msg = isset($postParams['msg']) ? mb_substr(trim($postParams['msg']), 0, 5000) : '';
        $trans = isset($postParams['msgtrans']) ? 1 : 0;
        $code = isset($postParams['code']) ? trim($postParams['code']) : '';
        $from = $systemUser->isValid() ? $systemUser->name : $name;
        // Проверяем на ошибки
        $error = [];
        $flood = false;

        if (!isset($postParams['token']) || !isset($_SESSION['token']) || $postParams['token'] != $_SESSION['token']) {
            $error[] = _t('Wrong data');
        }

        if (!$systemUser->isValid() && empty($name)) {
            $error[] = _t('You have not entered a name');
        }

        if (empty($msg)) {
            $error[] = _t('You have not entered the message');
        }

        if (isset($systemUser->ban['1']) || isset($systemUser->ban['13'])) {
            $error[] = _t('Access forbidden');
        }

        // CAPTCHA для гостей
        if (!$systemUser->isValid()
            && (empty($code) || mb_strlen($code) < 3 || strtolower($code) != strtolower($_SESSION['code']))
        ) {
            $error[] = _t('The security code is not correct');
        }

        unset($_SESSION['code']);

        if ($systemUser->isValid()) {
            // Антифлуд для зарегистрированных пользователей
            $flood = $tools->antiflood();
        } else {
            // Антифлуд для гостей
            $attr = [
                ['ip', '=', $request->getAttribute('ip')],
                ['browser', '=', $request->getAttribute('user_agent')],
                ['time', '>', (time() - 60)]
            ];
            $req = $db->prepare($builder->setTable('guest')->select(['time'])->where($attr));
            $req->execute($builder->placeholders());

            if ($time = $req->fetchColumn()) {
                $flood = 60 - (time() - $time);
            }
        }

        if ($flood) {
            $error = sprintf(_t('You cannot add the message so often. Please, wait %d seconds.'), $flood);
        }

        if (!$error) {
            // Проверка на одинаковые сообщения
            $req = $db->prepare(
                $builder->setTable('guest')->select(['text'])
                ->where([['user_id', '=', $systemUser->id]])
                ->order(['time'], 'desc')
            );
            $req->execute($builder->placeholders());

            if ($req->fetchColumn() == $msg) {
                header('Location: ?');
                exit;
            }
        }

        if (!$error) {
            // Вставляем сообщение в базу
            $db->prepare($builder
                ->setTable('guest')
                ->insert()
                ->fields([
                    'adm',
                    'time',
                    'user_id',
                    'name',
                    'text',
                    'ip',
                    'browser',
                    'otvet'
            ]))->execute([
                    $admset,
                    time(),
                    $systemUser->id,
                    $from,
                    $msg,
                    $request->getAttribute('ip'),
                    $request->getAttribute('user_agent'),
                    ''
                ]);

            // Фиксируем время последнего поста (антиспам)
            if ($systemUser->isValid()) {
                $postguest = $systemUser->postguest + 1;
                $req = $db->prepare($builder->setTable('users')->update([
                    'postguest' => $postguest, 'lastpost' => time()
                ])->where([['id', '=', $systemUser->id]]));
                $req->execute($builder->placeholders());
            }
            header('Location: ?');
        } else {
            echo $tools->displayError($error, '<a href="index.php">' . _t('Back') . '</a>');
        }
        break;

    case 'otvet':
        // Добавление "ответа Админа"
        if ($systemUser->rights >= 6 && $id) {
            if (isset($postParams['submit'])
                && isset($postParams['token'])
                && isset($_SESSION['token'])
                && $postParams['token'] == $_SESSION['token']
            ) {
                $reply = isset($postParams['otv']) ? mb_substr(trim($postParams['otv']), 0, 5000) : '';
                $req = $db->prepare($builder
                    ->setTable('guest')
                    ->update([
                        'admin' => $systemUser->name,
                        'otvet' => $reply,
                        'otime' => time()
                    ])
                    ->where([['id', '=', $id]])
                );
                $req->execute($builder->placeholders());
                header('Location: ?');
            } else {
                echo '<div class="phdr"><a href="index.php"><b>' . _t('Guestbook') . '</b></a> | ' . _t('Reply') . '</div>';
                $req = $db->prepare($builder
                    ->setTable('guest')
                    ->select()
                    ->where([['id', '=', $id]])
                );
                $req->execute($builder->placeholders());
                $res = $req->fetch();
                $token = mt_rand(1000, 100000);
                $_SESSION['token'] = $token;

                echo '<div class="menu">' .
                    '<div class="quote"><b>' . $res['name'] . '</b>' .
                    '<br />' . $tools->checkout($res['text']) . '</div>' .
                    '<form name="form" action="index.php?act=otvet&amp;id=' . $id . '" method="post">' .
                    '<p><h3>' . _t('Reply') . '</h3>' . $bbcode->buttons('form', 'otv') .
                    '<textarea rows="' . $userConfig->fieldHeight . '" name="otv">' . $tools->checkout($res['otvet']) . '</textarea></p>' .
                    '<p><input type="submit" name="submit" value="' . _t('Reply') . '"/></p>' .
                    '<input type="hidden" name="token" value="' . $token . '"/>' .
                    '</form></div>' .
                    '<div class="phdr"><a href="index.php">' . _t('Back') . '</a></div>';
            }
        }
        break;

    case
    'edit':
        // Редактирование поста
        if ($systemUser->rights >= 6 && $id) {
            if (isset($postParams['submit'])
                && isset($postParams['token'])
                && isset($_SESSION['token'])
                && $postParams['token'] == $_SESSION['token']
            ) {
                $req = $db->prepare($builder
                    ->setTable('guest')
                    ->select(['edit_count'])
                    ->where([['id', '=', $id]])
                );
                $req->execute($builder->placeholders());
                $edit_count = $req->fetchColumn() + 1;
                $msg = isset($postParams['msg']) ? mb_substr(trim($postParams['msg']), 0, 5000) : '';

                $db->prepare($builder
                    ->setTable('guest')
                    ->update()
                    ->fields([
                        'text',
                        'edit_who',
                        'edit_time',
                        'edit_count'
                    ]))->execute([
                        $msg,
                        $systemUser->name,
                        time(),
                        $edit_count,
                        $id,
                ]);
                header('Location: ?');
            } else {
                $token = mt_rand(1000, 100000);
                $_SESSION['token'] = $token;
                $req = $db->prepare($builder->setTable('guest')
                    ->select()
                    ->where([['id', '=', $id]]));
                $req->execute($builder->placeholders());
                $res = $req->fetch();
                $text = htmlentities($res['text'], ENT_QUOTES, 'UTF-8');
                echo '<div class="phdr"><a href="index.php"><b>' . _t('Guestbook') . '</b></a> | ' . _t('Edit') . '</div>' .
                    '<div class="rmenu">' .
                    '<form name="form" action="index.php?act=edit&amp;id=' . $id . '" method="post">' .
                    '<p><b>' . _t('Author') . ':</b> ' . $res['name'] . '</p><p>';
                echo $bbcode->buttons('form', 'msg');
                echo '<textarea rows="' . $userConfig->fieldHeight . '" name="msg">' . $text . '</textarea></p>' .
                    '<p><input type="submit" name="submit" value="' . _t('Save') . '"/></p>' .
                    '<input type="hidden" name="token" value="' . $token . '"/>' .
                    '</form></div>' .
                    '<div class="phdr"><a href="index.php">' . _t('Back') . '</a></div>';
            }
        }
        break;

    case 'clean':
        // Очистка Гостевой
        if ($systemUser->rights >= 7) {
            if (isset($postParams['submit'])) {
                // Проводим очистку Гостевой, согласно заданным параметрам
                $adm = isset($_SESSION['ga']) ? 1 : 0;
                $cl = isset($postParams['cl']) ? intval($postParams['cl']) : '';

                switch ($cl) {
                    case '1':
                        // Чистим сообщения, старше 1 дня
                        $sql = $builder
                            ->setTable('guest')
                            ->delete()
                            ->where([
                                ['adm', '=', $adm],
                                ['time', '<', time() - 86400],
                            ]);
                        $db->prepare($builder
                            ->setTable('guest')
                            ->delete()
                            ->where([
                                ['adm', '=', $adm],
                                ['time', '<', time() - 86400]
                            ]))
                            ->execute($builder->placeholders());
                        echo '<p>' . _t('All messages older than 1 day were deleted') . '</p>';
                        break;

                    case '2':
                        // Проводим полную очистку
                        $db->prepare($builder
                            ->setTable('guest')
                            ->delete($adm, 'adm'))
                            ->execute($builder->placeholders());
                        echo '<p>' . _t('Full clearing is finished') . '</p>';
                        break;
                    default :
                        // Чистим сообщения, старше 1 недели
                        $db->prepare($builder
                            ->setTable('guest')
                            ->delete()
                            ->where([
                                ['adm', '=', $adm],
                                ['time', '<', time() - 604800]
                            ]))
                            ->execute($builder->placeholders());
                        echo '<p>' . _t('All messages older than 1 week were deleted') . '</p>';
                }

                $db->query("OPTIMIZE TABLE `guest`");
                echo '<p><a href="index.php">' . _t('Guestbook') . '</a></p>';
            } else {
                // Запрос параметров очистки
                echo '<div class="phdr"><a href="index.php"><b>' . _t('Guestbook') . '</b></a> | ' . _t('Clear') . '</div>' .
                    '<div class="menu">' .
                    '<form id="clean" method="post" action="index.php?act=clean">' .
                    '<p><h3>' . _t('Clearing parameters') . '</h3>' .
                    '<input type="radio" name="cl" value="0" checked="checked" />' . _t('Older than 1 week') . '<br />' .
                    '<input type="radio" name="cl" value="1" />' . _t('Older than 1 day') . '<br />' .
                    '<input type="radio" name="cl" value="2" />' . _t('Clear all') . '</p>' .
                    '<p><input type="submit" name="submit" value="' . _t('Clear') . '" /></p>' .
                    '</form></div>' .
                    '<div class="phdr"><a href="index.php">' . _t('Cancel') . '</a></div>';
            }
        }
        break;

    case 'ga':
        // Переключение режима работы Гостевая / Админ-клуб
        if ($systemUser->rights >= 1 || in_array($systemUser->id, $guestAccess)) {
            if (isset($queryParams['do']) && $queryParams['do'] == 'set') {
                $_SESSION['ga'] = 1;
            } else {
                unset($_SESSION['ga']);
            }
        }

    default:
        // Отображаем Гостевую, или Админ клуб
        if (!$config->mod_guest) {
            echo '<div class="alarm">' . _t('The guestbook is closed') . '</div>';
        }

        echo '<div class="phdr"><b>' . _t('Guestbook') . '</b></div>';

        if ($systemUser->rights > 0 || in_array($systemUser->id, $guestAccess)) {
            $menu = [
                isset($_SESSION['ga']) ? '<a href="index.php?act=ga">' . _t('Guestbook') . '</a>' : '<b>' . _t('Guestbook') . '</b>',
                isset($_SESSION['ga']) ? '<b>' . _t('Admin Club') . '</b>' : '<a href="index.php?act=ga&amp;do=set">' . _t('Admin Club') . '</a>',
                $systemUser->rights >= 7 ? '<a href="index.php?act=clean">' . _t('Clear') . '</a>' : '',
            ];
            echo '<div class="topmenu">' . implode(' | ', array_filter($menu)) . '</div>';
        }

        // Форма ввода нового сообщения
        if (($systemUser->isValid() || $config->mod_guest == 2) && !isset($systemUser->ban['1']) && !isset($systemUser->ban['13'])) {
            $token = mt_rand(1000, 100000);
            $_SESSION['token'] = $token;
            echo '<div class="gmenu"><form name="form" action="index.php?act=say" method="post">';

            if (!$systemUser->isValid()) {
                echo _t('Name') . ' (max 25):<br><input type="text" name="name" maxlength="25"/><br>';
            }

            echo '<b>' . _t('Message') . '</b> <small>(max 5000)</small>:<br>';
            echo $bbcode->buttons('form', 'msg');
            echo '<textarea rows="' . $userConfig->fieldHeight . '" name="msg"></textarea><br>';

            if (!$systemUser->isValid()) {
                // CAPTCHA для гостей
                $captcha = new Mobicms\Captcha\Captcha;
                $code = $captcha->generateCode();
                $_SESSION['code'] = $code;

                echo '<img alt="' . _t('Verification code') . '" width="' . $captcha->width . '" height="' . $captcha->height . '" src="' . $captcha->generateImage($code) . '"/><br />' .
                    '<input type="text" size="5" maxlength="5"  name="code"/>&#160;' . _t('Symbols on the picture') . '<br />';
            }
            echo '<input type="hidden" name="token" value="' . $token . '"/>' .
                '<input type="submit" name="submit" value="' . _t('Send') . '"/></form></div>';
        } else {
            echo '<div class="rmenu">' . _t('For registered users only') . '</div>';
        }

        $sql = $builder
            ->setTable('guest')
            ->count()
            ->where([['adm', '=', isset($_SESSION['ga']) ? 1 : 0]]);
        $req = $db->prepare($builder
                ->setTable('guest')
                ->count('*', 'count')
                ->where([['adm', '=', isset($_SESSION['ga']) ? 1 : 0]]));
        $req->execute($builder->placeholders());
        $total = $req->fetchColumn();
        echo '<div class="phdr"><b>' . _t('Comments') . '</b></div>';

        if ($total > $userConfig->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('index.php?', $total) . '</div>';
        }

        if ($total) {
            // Запрос для Админ клуба или обычной Гастивухи
            $admField = (isset($_SESSION['ga']) && ($systemUser->rights >= 1 || in_array($systemUser->id, $guestAccess))) ? 1 : 0;
            if ($admField) {
                echo '<div class="rmenu"><b>АДМИН-КЛУБ</b></div>';
            }
            $req = $db->prepare($builder
                ->setTable('guest')
                ->select([
                    'guest.*',
                    'guest.id' => 'gid',
                    'users.rights',
                    'users.lastdate',
                    'users.sex',
                    'users.status',
                    'users.datereg',
                    'users.id'
                ])
                ->join('users', null, [['user_id', 'id']])
                ->where([
                    ['guest.adm', '=', $admField]
                ])
                ->order(['time'], 'desc')
                ->limit($tools->getPgStart(), $userConfig->kmess, 'limit'));

            $req->execute($builder->placeholders());

            for ($i = 0; $res = $req->fetch(); ++$i) {
                $text = '';
                echo $i % 2 ? '<div class="list2">' : '<div class="list1">';

                if (!$res['id']) {
                    // Запрос по гостям
                    $res_g = $db->prepare($builder
                        ->setTable('cms_sessions')
                        ->select(['lastdate'])
                        ->where([
                            ['session_id' , '=', md5($res['ip'] . $res['browser'])]
                        ])
                        ->limit(1))
                        ->fetchColumn();
                    $res['lastdate'] = $res_g;
                }

                // Время создания поста
                $text = ' <span class="gray">(' . $tools->displayDate($res['time']) . ')</span>';

                if ($res['user_id']) {
                    // Для зарегистрированных показываем ссылки и смайлы
                    $post = $tools->checkout($res['text'], 1, 1);
                    $post = $tools->smilies($post, $res['rights'] >= 1 ? 1 : 0);
                } else {
                    // Для гостей обрабатываем имя и фильтруем ссылки
                    $res['name'] = $tools->checkout($res['name']);
                    $post = $tools->checkout($res['text'], 0, 2);
                    $post = preg_replace('~\\[url=(https?://.+?)\\](.+?)\\[/url\\]|(https?://(www.)?[0-9a-z\.-]+\.[0-9a-z]{2,6}[0-9a-zA-Z/\?\.\~&amp;_=/%-:#]*)~', '###', $post);
                    $replace = [
                        '.ru'   => '***',
                        '.com'  => '***',
                        '.biz'  => '***',
                        '.cn'   => '***',
                        '.in'   => '***',
                        '.net'  => '***',
                        '.org'  => '***',
                        '.info' => '***',
                        '.mobi' => '***',
                        '.wen'  => '***',
                        '.kmx'  => '***',
                        '.h2m'  => '***',
                    ];

                    $post = strtr($post, $replace);
                }

                if ($res['edit_count']) {
                    // Если пост редактировался, показываем кем и когда
                    $post .= '<br /><span class="gray"><small>Изм. <b>' . $res['edit_who'] . '</b> (' . $tools->displayDate($res['edit_time']) . ') <b>[' . $res['edit_count'] . ']</b></small></span>';
                }

                if (!empty($res['otvet'])) {
                    // Ответ Администрации
                    $otvet = $tools->checkout($res['otvet'], 1, 1);
                    $otvet = $tools->smilies($otvet, 1);
                    $post .= '<div class="reply"><b>' . $res['admin'] . '</b>: (' . $tools->displayDate($res['otime']) . ')<br>' . $otvet . '</div>';
                }

                if ($systemUser->rights >= 6) {
                    $subtext = '<a href="index.php?act=otvet&amp;id=' . $res['gid'] . '">' . _t('Reply') . '</a>' .
                        ($systemUser->rights >= $res['rights'] ? ' | <a href="index.php?act=edit&amp;id=' . $res['gid'] . '">' . _t('Edit') . '</a> | <a href="index.php?act=delpost&amp;id=' . $res['gid'] . '">' . _t('Delete') . '</a>' : '');
                } else {
                    $subtext = '';
                }

                $arg = [
                    'header' => $text,
                    'body'   => $post,
                    'sub'    => $subtext,
                ];

                echo $tools->displayUser($res, $arg);
                echo '</div>';
            }
        } else {
            echo '<div class="menu"><p>' . _t('The guestbook is empty.<br><strong>Be the first! :)</strong>') . '</p></div>';
        }

        echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

        if ($total > $userConfig->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('index.php?', $total) . '</div>' .
                '<p><form action="index.php" method="get"><input type="text" name="page" size="2"/>' .
                '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/></form></p>';
        }

        break;
}

echo $view->render('system::app/legacy', [
    'title'   => _t('Guestbook'),
    'content' => ob_get_clean(),
]);
