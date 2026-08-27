<?php

namespace Inova\NovaAdmin\Exceptions;

use RuntimeException;

/**
 * webdeploy 站点广告配置下发协议的校验失败。
 *
 * 与运行时异常区分，便于在结果 marker 里给出可读原因。
 */
class AdsProtocolException extends RuntimeException {}
