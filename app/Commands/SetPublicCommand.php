<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class SetPublicCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Запуск / перезапуск бота';


    public function handle()
    {
        $message = '<b>Привет!</b>'
            . PHP_EOL . '<i>Если хочешь узнать, что я могу, жми </i>'
            . PHP_EOL . '<u>/help</u>';
        $this->replyWithMessage(['text' => $message, 'parse_mode' => 'HTML']);
    }
}
