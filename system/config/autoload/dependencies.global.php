<?php

return [
    'dependencies' => [
        'factories' => [
            Mobicms\Api\BbcodeInterface::class      => Mobicms\Bbcode::class,
            Mobicms\Api\ConfigInterface::class      => Mobicms\Config\ConfigFactory::class,
            Mobicms\Api\EnvironmentInterface::class => Mobicms\Environment::class,
            Mobicms\Api\ToolsInterface::class       => Mobicms\Tools\Utilites::class,
            Mobicms\Api\UserInterface::class        => Mobicms\Checkpoint\UserFactory::class,
            PDO::class                              => Mobicms\Database\PdoFactory::class,

            'counters' => Mobicms\Counters::class,
        ],

        'aliases' => [],
    ],
];
