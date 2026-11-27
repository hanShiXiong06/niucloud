<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;
interface Transport { public function send(string $method, string $path, array $body = []): array; }
