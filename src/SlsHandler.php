<?php

namespace Ece2\HyperfExtSls;

use AlibabaCloud\OpenApiUtil\OpenApiUtilClient;
use AlibabaCloud\SDK\Sls\V20201230\Sls;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;
use Darabonba\OpenApi\Models\OpenApiRequest;
use Darabonba\OpenApi\Models\Params;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class SlsHandler extends AbstractProcessingHandler
{
    /**
     * @var Sls
     */
    private $client;

    private $config;

    public function __construct($config)
    {
        $this->config = $config;

        $this->client = new Sls(new Config([
            'accessKeyId' => $config['accessKeyId'],
            'accessKeySecret' => $config['accessKeySecret'],
            'endpoint' => $config['endpoint'],
        ]));
    }

    public function write(array $record): void
    {
        go(function () use ($record) {
            try {
                // 上下文中获取 traceId (request id)
                $context = $record['context'] ?? [];
                $traceId = $context['traceId'] ?? '';
                unset($context['traceId']);

                // 值转为 string
                $value2Str = function ($arr) use (&$value2Str) {
                    return array_map(function ($item) use (&$value2Str) {
                        return is_array($item) ? $value2Str($item) : (string) $item;
                    }, $arr);
                };
                $body = $value2Str([
                    '__topic__' => $this->config['topic'],
                    '__source__' => $this->config['source'],
                    '__logs__' => [
                        array_merge(
                            [
                                'message' => $record['message'] ?? '',
                                'channel' => $record['channel'] ?? '',
                                'level_name' => $record['level_name'] ?? '',
                            ],
                            $context
                        ),
                    ],
                    '__tags__' => [
                        'level' => $record['level'] ?? Logger::DEBUG,
                        'traceId' => $traceId,
                    ]
                ]);

                // 整理 SLS 数据, 发送请求
                $runtime = new RuntimeOptions([]);
                $req = new OpenApiRequest([
                    'hostMap' => [
                        'project' => $this->config['project'],
                    ],
                    'stream' => json_encode($body),
                ]);
                $params = new Params([
                    'action' => 'PutWebtracking',
                    'version' => '2020-12-30',
                    'protocol' => 'HTTPS',
                    'pathname' => sprintf('/logstores/%s/track', $this->config['logStoreName']),
                    'method' => 'POST',
                    'authType' => 'AK',
                    'style' => 'ROA',
                    'reqBodyType' => 'json',
                    'bodyType' => 'none',
                ]);

                $this->client->execute($params, $req, $runtime);
            } catch (\Throwable $e) {

            }
        });
    }
}
