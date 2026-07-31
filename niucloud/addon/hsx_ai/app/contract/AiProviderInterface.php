<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\contract;

interface AiProviderInterface
{
    /** @return array<int,array{id:string,name:string,owned_by:string}> */
    public function models(array $provider): array;

    /** @return array{connected:bool,model_count:int,models:array} */
    public function test(array $provider): array;

    /**
     * @return array{
     *   provider_request_id:string,
     *   model:string,
     *   content:string,
     *   reasoning_content?:string,
     *   finish_reason:string,
     *   first_token_ms?:int,
     *   usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}
     * }
     */
    public function chat(array $provider, array $request): array;
}
