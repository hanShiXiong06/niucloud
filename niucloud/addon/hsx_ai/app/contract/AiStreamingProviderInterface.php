<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\contract;

interface AiStreamingProviderInterface
{
    /**
     * 流式增量只负责展示，最终返回值仍按 AiProviderInterface::chat 的结果结构归一化。
     *
     * @param callable(array{type:string,delta?:string}):void $emit
     * @return array{
     *   provider_request_id:string,
     *   model:string,
     *   content:string,
     *   reasoning_content:string,
     *   finish_reason:string,
     *   first_token_ms:int,
     *   usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}
     * }
     */
    public function stream(array $provider, array $request, callable $emit): array;
}
