<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::post('/webhook', WebhookController::class);

//Route::get('/send', function () {
//    $token = '8067091828:AAGiyvqiEOt-IZM-OyluT1tapo03fPtLU0k';
//    $chatID = 1717491249;
//    $text = 'Hello world';
//    $data = http_build_query([
//        'text' => $text,
//        'chat_id' => $chatID
//    ]);
//    $url = "https://api.telegram.org/bot$token/sendMessage?{$data}";
//    file_get_contents($url);
//    return response('OK', 200);
//});
