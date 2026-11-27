<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

interface AiSpeechProviderInterface
{
    public function configure(array $config): self;

    public function speechToText($file, int $siteId): array;

    public function textToSpeech(string $text, int $siteId): array;

    public function test(int $siteId): array;
}
