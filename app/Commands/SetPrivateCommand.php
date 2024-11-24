<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class SetPrivateCommand extends Command
{
    protected string $name = 'set_private';
    protected string $description = 'Сделать портфель приватным (можно редактировать)';


    public function handle()
    {
        $message = '<i>Отлично, теперь придумай имя для портфеля! </i>';

        $this->replyWithMessage(['text' => $message, 'parse_mode' => 'HTML']);

        $this->getUpdate();
}}
