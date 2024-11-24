<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Telegram\Bot\BotsManager;

class WebhookController extends Controller
{
    protected BotsManager $botsManager;
    public function __construct(BotsManager $botsManager)
    {
        $this->botsManager = $botsManager;
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        $this->botsManager->bot()->commandsHandler(true);
        return response(null, 200);
    }
}
//namespace App\Http\Controllers;
//
//use App\Models\Portfolio;
//use App\Models\TelegramUser;
//use Illuminate\Http\Request;
//use Illuminate\Http\Response;
//use Telegram\Bot\BotsManager;
//use App\Commands\StartCommand;
//
//use App\Commands\CreatePortfolioCommand;
//
//class WebhookController extends Controller
//{
//    protected BotsManager $botsManager;
//    protected $bot;
//    protected  TelegramUser $telegramUser;
//
//    public function __construct(BotsManager $botsManager, TelegramUser $telegramUser)
//    {
//        $this->botsManager = $botsManager;
//        $this->telegramUser = $telegramUser;
//
//    }
//
//    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
//    {
//
//        $updateData = $request->all();
//        $update = new \Telegram\Bot\Objects\Update($updateData);
//
//        $chatId = $update->getMessage()->getChat()->getId();
//        $text = $update->getMessage()->getText();
//        $userData = $update->getMessage()->getFrom();
//        $userId = $userData->id;
//        $telegramUser = $this->telegramUser->where('user_id', '=', $userId)->first();
//
//        if ($text) {
//            switch ($text) {
//                case '/start':
//
//                    $startCommand = new StartCommand($telegramUser,  $bot = $this->botsManager->bot());
//                    $startCommand->setUpdate($update);
//                    $startCommand->handle();
//                    break;
//
//                case 'Создать портфель':
//                    $createPortfolioCommand = new CreatePortfolioCommand(new Portfolio());
//                    $createPortfolioCommand->setUpdate($update);
//                    $createPortfolioCommand->handle();
//                    break;
//
//                default:
//                    $this->botsManager->bot()->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => 'Неизвестная команда. Используйте /start.'
//                    ]);
//                    break;
//            }
//        }
//
//        return response()->json(['status' => 'success']);
//    }}
