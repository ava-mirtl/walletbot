<?php

namespace App\Commands;

use App\Models\TelegramUser;
use Illuminate\HTTP\Request;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class SearchPortfolioCommand extends Command
{
    protected string $name = 'search_portfolio';
    protected string $description = 'Поиск портфеля';
    protected TelegramUser $telegramUser;

    public function __construct(TelegramUser $telegramUser)
    {
        $this->telegramUser = $telegramUser;
    }
    public function handle()
    {

    }

    public function sendMessage( )
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
