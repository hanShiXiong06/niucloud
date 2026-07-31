<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\support;

use think\Response;

final class AiStreamResponse extends Response
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->contentType = 'text/event-stream';
        $this->init('', 200);
        $this->header([
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->code);
            foreach ($this->header as $name => $value) {
                header($name . (!is_null($value) ? ':' . $value : ''));
            }
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        while (ob_get_level() > 0) @ob_end_flush();
        ($this->callback)();
    }
}
