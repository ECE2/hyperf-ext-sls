<?php

namespace Ece2\HyperfExtSls;

use AlibabaCloud\SDK\Sls\V20201230\Sls;
use AlibabaCloud\Tea\Response;
use AlibabaCloud\Tea\Tea;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;
use Darabonba\OpenApi\Models\OpenApiRequest;
use Darabonba\OpenApi\Models\Params;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Hyperf\Guzzle\PoolHandler;
use Hyperf\Utils\Coroutine;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

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

        $this->replaceSlsClient();

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
                $this->getDowngradeLogger()->error(sprintf(
                    'sls 推送失败: %s (file: %s line: %d code: %d)',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getCode()
                ));
            }
        });
    }

    public function replaceSlsClient()
    {
        $handler = null;
        if (Coroutine::inCoroutine()) {
            $handler = make(PoolHandler::class, [
                'option' => [
                    'max_connections' => ((int) $this->config['maxGuzzleConnections']) ?: 50,
                ],
            ]);
        }

        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::mapResponse(static fn(ResponseInterface $response) => new Response($response)));

        Tea::config(['handler' => $stack]);
    }

    /**
     * 降级日志
     * @return \Hyperf\Logger\Logger|mixed
     */
    protected function getDowngradeLogger(): Logger
    {
        $handler = make(StreamHandler::class, ['stream' => BASE_PATH . '/runtime/logs/hyperf.log']);
        $handler->setFormatter(make(LineFormatter::class, [
            'dateFormat' => 'Y-m-d H:i:s',
            'allowInlineLineBreaks' => true
        ]));

        return make(\Hyperf\Logger\Logger::class, ['name' => 'sls_logger', 'handlers' => [$handler]]);
    }
}
