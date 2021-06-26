<?php

use App\Http\Controllers\Api\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Telegram\Bot\Actions;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('webhook', [TelegramController::class, 'receiveUpdate']);

Route::post('webhook2', function (Request $request) {
    Log::info($request->all());
    $data = $request->all();

    // This will update the chat status to typing...
    Telegram::sendChatAction(['chat_id' => $data['message']['from']['id'], 'action' => Actions::TYPING]);

    Telegram::sendMessage(['chat_id' => $data['message']['from']['id'], 'text' => 'Goblog']);


    // Telegram::replyWithMessage(['text' => $response]);

    // $keyboard = [
    //     ['7', '8', '9'],
    //     ['4', '5', '6'],
    //     ['1', '2', '3'],
    //          ['0']
    // ];

    // $params = [
    //     'keyboard' => $keyboard, 
    //     'resize_keyboard' => true, 
    //     'one_time_keyboard' => true
    // ];

    // Log::info($data['message']['message_id']);

    // $reply_markup = Keyboard::make($params);
    
    
    // $response = Telegram::sendMessage([
    //     'chat_id' => $data['message']['from']['id'], 
    //     'text' => 'Hello World', 
    //     'reply_markup' => $reply_markup
    // ]);
    
    // $messageId = $response->getMessageId();
    // $update = Telegram::commandsHandler(true);
});

Route::any('webhook/update', function () {
    $updates = Telegram::getWebhookUpdates();
    dd($updates);
});

// Route::get('/setwebhook', function () {
// 	$response = Telegram::setWebhook(['url' => 'https://d79e77f5.ngrok.io/42yUojv1YQPOssPEpn5i3q6vjdhh7hl7djVWDIAVhFDRMAwZ1tj0Og2v4PWyj4PZ/webhook']);
// 	dd($response);
// });