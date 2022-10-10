## Hyperf SLS 日志扩展

安装

```shell
composer require ece2/hyperf-ext-sls -W --ignore-platform-reqs
```

初始化, 仅仅用在刚刚创建的 hyperf 项目, 不然会覆盖项目代码

```shell
php bin/hyperf.php vendor:publish ece2/hyperf-ext-sls -f
```

.env 配置内容, 查看 publish 后的 config/autoload/logger.php
