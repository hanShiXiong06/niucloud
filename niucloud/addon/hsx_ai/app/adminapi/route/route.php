<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('ai', function () {
    Route::get('config/model', 'addon\hsx_ai\app\adminapi\controller\Config@modelInfo');
    Route::post('config/model', 'addon\hsx_ai\app\adminapi\controller\Config@saveModel');
    Route::get('config/integration', 'addon\hsx_ai\app\adminapi\controller\Config@integrationInfo');
    Route::post('config/integration', 'addon\hsx_ai\app\adminapi\controller\Config@saveIntegration');
    Route::get('config/speech', 'addon\hsx_ai\app\adminapi\controller\Config@speechInfo');
    Route::post('config/speech', 'addon\hsx_ai\app\adminapi\controller\Config@saveSpeech');
    Route::get('playground/config', 'addon\hsx_ai\app\adminapi\controller\Config@playgroundInfo');
    Route::post('playground/speech/stt', 'addon\hsx_ai\app\adminapi\controller\Config@playgroundSpeechToText');
    Route::post('playground/speech/tts', 'addon\hsx_ai\app\adminapi\controller\Config@playgroundTextToSpeech');
    Route::post('speech/test', 'addon\hsx_ai\app\adminapi\controller\Config@testSpeech');
    Route::post('provider/test', 'addon\hsx_ai\app\adminapi\controller\Config@testProvider');
    Route::post('provider/models', 'addon\hsx_ai\app\adminapi\controller\Config@syncModels');
    Route::post('execute', 'addon\hsx_ai\app\adminapi\controller\Config@execute');
    Route::post('stream', 'addon\hsx_ai\app\adminapi\controller\Config@stream');
    Route::get('logs', 'addon\hsx_ai\app\adminapi\controller\Log@lists');
    Route::get('conversations', 'addon\hsx_ai\app\adminapi\controller\Conversation@lists');
    Route::get('conversations/:id', 'addon\hsx_ai\app\adminapi\controller\Conversation@detail');
    Route::get('risks', 'addon\hsx_ai\app\adminapi\controller\Risk@lists');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);
