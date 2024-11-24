<?php
namespace App\Commands;

use App\Models\TelegramUser;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Запуск / перезапуск бота';
    protected TelegramUser $telegramUser;

    public function __construct(TelegramUser $telegramUser)
    {
        $this->telegramUser = $telegramUser;
    }

    public function handle()
    {
        $userData = $this->getUpdate()->message->from;
        $userId = $userData->id;
        $telegramUser = $this->telegramUser->where('user_id', '=', $userId)->first();

        if ($telegramUser) {
            $this->sendMessageForOldUser();
        } else {
            $this->addNewTelegramUser($userData);
            $this->sendMessageForNewUser();
        }
    }

    public function addNewTelegramUser($userData): void
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

    public function sendMessageForNewUser(): void
    {
        $reply_markup = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true)
            ->row([
                Keyboard::button(['Создать портфель']),
                Keyboard::button(['Поиск портфеля']),
            ]);
        $this->replyWithMessage([
            'text' => 'Привет, давай познакомимся! Я бот, который поможет держать всю информацию в одном месте',
            'reply_markup' => $reply_markup,
        ]);

    }

    public function sendMessageForOldUser(): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                [Keyboard::inlineButton(['text' => 'Создать портфель', 'callback_data' => '/create_portfolio'])
            ,
                Keyboard::inlineButton(['text' => 'Поиск портфеля', 'callback_data' => '/search_portfolio'])]
            );

        $this->replyWithMessage(['text' => 'Привет! Рад снова видеть тебя! Выбери действие:', 'reply_markup' => $keyboard]);
    }
}

//
//namespace App\Commands;
//
//use App\Models\TelegramUser;
//use Telegram\Bot\Commands\Command;
//use Telegram\Bot\Objects\User;
//
//class StartCommand extends Command
//{
//    protected string $name = 'start';
//    protected string $description = 'Запуск / Перезапуск бота';
//    protected TelegramUser $telegramUser;
//
//    public function __construct(TelegramUser $telegramUser)
//    {
//        $this->telegramUser = $telegramUser;
//    }
//
//    public function handle()
//    {
//        $userData = $this->getUpdate()->message->from;
//        $userId = $userData->id;
//        $telegramUser = $this->telegramUser->where('user_id', '=', $userId)->first();
//
//        if ($telegramUser) {
//            $this->sendAnswerForOldUsers();
//        } else {
//            $this->addNewTelegramUser($userData);
//            $this->sendAnswerForNewUsers();
//        }
//    }
//
//    /**
//     * Добавление пользователя в базу данных.
//     * @param User $userData
//     * @return void
//     */
//    public function addNewTelegramUser(User $userData)
//    {
//        $this->telegramUser->insert([
//            'user_id' => $userData->id,
//            'username' => $userData->username,
//            'first_name' => $userData->first_name,
//            'last_name' => $userData->last_name,
//            'is_premium' => $userData->is_premium,
//            'is_bot' => $userData->is_bot,
//            'status' => $userData->status,
//        ]);
//    }
//
//    /**
//     * Ответ старому пользователю.
//     * @return void
//     */
//    public function sendAnswerForOldUsers(): void
//    {
//        $this->replyWithMessage([
//            'text' => 'Рады видеть вас снова!🥳'
//        ]);
//    }
//
//    /**
//     * Ответ новому пользователю.
//     * @return void
//     */
//    public function sendAnswerForNewUsers(): void
//    {
//        $this->replyWithMessage([
//            'text' => 'Добро пожаловать в наш телеграм бот!'
//        ]);
//    }
//}
