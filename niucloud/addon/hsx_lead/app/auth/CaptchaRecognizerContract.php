<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\auth;

/** A single bounded image task; scheduling, consent and polling limits belong to the broker. */
interface CaptchaRecognizerContract
{
    public function create(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $dataUri, int $length = 4): string;

    /** @return array{status:'processing'|'ready',text?:string,cost:?string} */
    public function result(#[\SensitiveParameter] string $apiKey, string $taskId, int $length = 4): array;

    public function balance(#[\SensitiveParameter] string $apiKey): string;
}
