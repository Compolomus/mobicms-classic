<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

namespace Mobicms\System;

use Mobicms\Api\EnvironmentInterface;
use Mobicms\System\Exception\IpBanException;
use Psr\Container\ContainerInterface;

class IpBan
{
    public function __construct(ContainerInterface $container)
    {
        /** @var EnvironmentInterface $env */
        $env = $container->get(EnvironmentInterface::class);

        /** @var \PDO $db */
        $db = $container->get(\PDO::class);

        $req = $db->query("SELECT `ban_type`, `link` FROM `cms_ban_ip`
          WHERE '" . $env->getIp() . "' BETWEEN `ip1` AND `ip2`
          " . ($env->getIpViaProxy() ? " OR '" . $env->getIpViaProxy() . "' BETWEEN `ip1` AND `ip2`" : '') . "
          LIMIT 1
        ");

        if ($req->rowCount()) {
            $res = $req->fetch();

            switch ($res['ban_type']) {
                case 2:
                    throw new IpBanException('Location: ' . (!empty($res['link']) ? $res['link'] : 'http://example.com'));
                    break;
                case 3:
                    //TODO: реализовать запрет регистрации
                    //self::$deny_registration = true;
                    break;
                default :
                    throw new IpBanException('HTTP/1.0 404 Not Found');
            }
        }

        return $this;
    }
}
