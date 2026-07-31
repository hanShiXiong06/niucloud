<?php
declare(strict_types=1);

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('ai/assistant', function () {
    Route::get('capability', 'addon\hsx_ai\app\api\controller\Assistant@capability');
    Route::post('chat', 'addon\hsx_ai\app\api\controller\Assistant@chat');
    Route::post('stream', 'addon\hsx_ai\app\api\controller\Assistant@stream');
    Route::post('speech/stt', 'addon\hsx_ai\app\api\controller\Assistant@speechToText');
    Route::post('speech/tts', 'addon\hsx_ai\app\api\controller\Assistant@textToSpeech');
})->middleware([ApiChannel::class, ApiCheckToken::class, ApiLog::class]);
