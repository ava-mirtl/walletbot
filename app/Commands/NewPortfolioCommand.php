<?php

namespace App\Commands;

use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class NewPortfolioCommand extends Command
{
    protected string $name = 'new_portfolio';
    protected string $description = 'Создать новый портфель';

    public function handle()
    {
        $reply_markup = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true)
            ->row([
                Keyboard::button('Приватный'),
                Keyboard::button('Публичный'),
            ]);

        $this->replyWithMessage([
            'text' => 'Ура, давай скорее создадим новый портфель! Выбери, каким он будет - приватным или публичным',
            'reply_markup' => $reply_markup,
        ]);

        $update = Telegram::getWebhookUpdates();
        $message = $update->getMessage();
        $buttonValue = $message->getText();
        $command = new NewPortfolioCommand();

        if (in_array($buttonValue, ['Приватный', 'Публичный'])) {
            $command->processButton($buttonValue);
        }
    }

    public function processButton($buttonValue): void
    {
        if ($buttonValue === 'Приватный') {
            $this->replyWithMessage(['text' => 'Вы выбрали приватный портфель.']);
        } elseif ($buttonValue === 'Публичный') {
            $this->replyWithMessage(['text' => 'Вы выбрали публичный портфель.']);
        }
    }
}


