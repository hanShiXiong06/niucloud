<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use addon\hsx_ai\app\contract\AiProviderInterface;
use addon\hsx_ai\app\provider\OpenAiCompatibleProvider;
use core\exception\CommonException;

final class AiProviderRegistry
{
    public function resolve(string $driver): AiProviderInterface
    {
        $providers = [
            'openai_compatible' => OpenAiCompatibleProvider::class,
        ];
        foreach ((array)event('HsxAiProviderRegistry', []) as $result) {
            if (!is_array($result)) continue;
            foreach ($result as $key => $class) {
                if (is_string($key) && is_string($class) && $key !== '' && $class !== '') {
                    $providers[$key] = $class;
                }
            }
        }
        $class = $providers[$driver] ?? '';
        if ($class === '' || !class_exists($class)) {
            throw new CommonException('不支持的模型通道类型：' . $driver);
        }
        $provider = new $class();
        if (!$provider instanceof AiProviderInterface) {
            throw new CommonException('模型通道必须实现 AiProviderInterface');
        }
        return $provider;
    }
}
