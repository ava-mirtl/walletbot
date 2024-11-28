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
        $telegramUser = $this->telegramUser->where('id', '=', $userId)->first();

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
            'id' => $userData->id,
            'username' => $userData->username,
            'first_name' => $userData->first_name,
            'last_name' => $userData->last_name,
            'is_premium' => $userData->is_premium,
            'is_bot' => $userData->is_bot,
            'status' => $userData->status,
        ]);
    }

    public function sendMessageForNewUser(): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                [Keyboard::inlineButton(['text' => 'Создать портфель', 'callback_data' => '/create_portfolio'])
                    ,
                    Keyboard::inlineButton(['text' => 'Поиск портфеля', 'callback_data' => '/search_portfolio'])]
            );
        $this->replyWithMessage(['text' => 'Привет, давай познакомимся! Я бот, который поможет держать всю информацию в одном месте. Выбери действие:', 'reply_markup' => $keyboard]);
    }

    public function sendMessageForOldUser(): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                [Keyboard::inlineButton(['text' => 'Создать портфель', 'callback_data' => '/create_portfolio'])
            ,
                Keyboard::inlineButton(['text' => 'Поиск портфеля', 'callback_data' => '/search_portfolio'])]
            )->row(
                [Keyboard::inlineButton(['text' => 'Выбрать тип портфеля', 'callback_data' => '/choose_type']),
                    Keyboard::inlineButton(['text' => 'Настройки', 'callback_data' => '/settings'])]
            );
        $this->replyWithMessage(['text' => 'Привет! Рад снова видеть тебя! Выбери действие:', 'reply_markup' => $keyboard]);
    }
}
