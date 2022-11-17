<?php

declare(strict_types=1);

return [
    'default' => [
        'handler' => [
            'class' => \Ece2\HyperfExtSls\SlsHandler::class,
            'constructor' => [
                'config' => [
                    'accessKeyId' => env('SLS_ACCESS_KEY_ID'), // 阿里云 access key id
                    'accessKeySecret' => env('SLS_ACCESS_KEY_SECRET'), // 阿里云 access key secret
                    'endpoint' => env('SLS_ENDPOINT'), // endpoint eg: cn-shanghai.log.aliyuncs.com
                    'project' => env('SLS_PROJECT'), // project 项目名称
                    'logStoreName' => env('SLS_LOG_STORE_NAME'), // logStoreName 日志库名
                    'topic' => env('SLS_TOPIC') ?: 'api_log', // topic 日志主题
                    'source' => env('SLS_SOURCE') ?: env('APP_NAME'), // 源, 默认系统名就行
                    'maxGuzzleConnections' => env('SLS_MAX_GUZZLE_CONNECTIONS') ?: 50, // guzzle client pool max connections
                ],
            ],
        ],
    ],
];
