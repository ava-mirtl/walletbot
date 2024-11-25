<?php

namespace App\Commands;

use App\Models\TelegramUser;
use Illuminate\HTTP\Request;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class SearchPortfolioCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Запуск / перезапуск бота';
    protected TelegramUser $telegramUser;

    public function __construct(TelegramUser $telegramUser)
    {
        $this->telegramUser = $telegramUser;
    }
    public function getMe()
    {
        $response = $this->telegram->getMe();
        return $response;
    }
    public function handleRequest(Request $request)
    {
        $this->chat_id = $request['message']['chat']['id'];
        $this->username = $request['message']['from']['username'];
        $this->text = $request['message']['text'];

        switch ($this->text) {
            case '/start':
            case '/menu':
                $this->showMenu();
                break;
            case '/getGlobal':
                $this->showGlobal();
                break;
            case '/getTicker':
                $this->getTicker();
                break;
            case '/getCurrencyTicker':
                $this->getCurrencyTicker();
                break;
            default:
                $this->checkDatabase();
        }
    }
    public function handle()
    {
        $userData = $this->getUpdate()->message->from;
        $userId = $userData->id;
        $telegramUser = $this->telegramUser->where('user_id','=', $userId )->first();

        if($telegramUser){
           $this->sendMessageForOldUser();
        }
        else{
            $this->addNewTelegramUser($userData);
            $this->sendMessageForNewUser();
        }
        $userAnswer = $this->getUpdate()->message->text;
        dd($userAnswer);
    }
    public function addNewTelegramUser( $userData)
    {
        $this->telegramUser->insert([
            'user_id' => $userData->id,
            'username' => $userData->username ?? null,
            'first_name' => $userData->first_name,
            'last_name' => $userData->last_name,
            'is_premium' => $userData->is_premium,
            'is_bot' => $userData->is_bot,
            'status' => $userData->status,
        ]);
    }
    public function sendMessageForNewUser( )
    {
        $reply_markup = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true)
            ->row([
                Keyboard::button('Создать портфель'),
                Keyboard::button('Поиск портфеля'),
            ]);

        $this->replyWithMessage([
            'text' => 'Привет, давай познакомимся! Я бот, который поможет держать всю информацию в одном месте',
            'reply_markup' => $reply_markup,
        ]);

    }
    public function sendMessageForOldUser( )
    {
        $reply_markup = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true)
            ->row([
                Keyboard::button('Создать портфель'),
                Keyboard::button('Поиск портфеля'),
            ]);

        $this->replyWithMessage([
            'text' => 'Привет! Рад снова видеть тебя! Выбери действие:',
            'reply_markup' => $reply_markup,
        ]);
    }
}
