<?php
declare(strict_types=1);

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('ai/assistant', function () {
    Route::get('capability', 'addon\hsx_ai\app\api\controller\Assistant@capability');
    Route::get('conversations', 'addon\hsx_ai\app\api\controller\Assistant@conversations');
    Route::get('conversations/:id/messages', 'addon\hsx_ai\app\api\controller\Assistant@messages');
    Route::post('conversations/:id/archive', 'addon\hsx_ai\app\api\controller\Assistant@archive');
    Route::post('chat', 'addon\hsx_ai\app\api\controller\Assistant@chat');
    Route::post('stream', 'addon\hsx_ai\app\api\controller\Assistant@stream');
    Route::post('speech/stt', 'addon\hsx_ai\app\api\controller\Assistant@speechToText');
    Route::post('speech/tts', 'addon\hsx_ai\app\api\controller\Assistant@textToSpeech');
})->middleware([ApiChannel::class, ApiCheckToken::class, ApiLog::class]);

// 项目介绍页允许游客咨询公开知识；ApiCheckToken(false) 仍负责识别站点和可选会员身份。
Route::group('ai/project-assistant', function () {
    Route::get(':projectId/capability', 'addon\hsx_ai\app\api\controller\ProjectAssistant@capability');
    Route::post(':projectId/chat', 'addon\hsx_ai\app\api\controller\ProjectAssistant@chat');
    Route::post(':projectId/stream', 'addon\hsx_ai\app\api\controller\ProjectAssistant@stream');
    Route::post(':projectId/speech/tts', 'addon\hsx_ai\app\api\controller\ProjectAssistant@textToSpeech');
})->middleware(ApiChannel::class)->middleware(ApiCheckToken::class, false)->middleware(ApiLog::class);
