<?php

namespace App\Commands;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Session;

class CreatePortfolioCommand
{
    protected string $name = '/create_portfolio';
    protected string $description = 'Запуск / Перезапуск бота';
    protected Portfolio $portfolio;
    public function __construct( Portfolio $portfolio)
    {
        $this->portfolio = $portfolio;
    }


    public function handlePortfolioName($chatId, $name)
    {
        Session::put('portfolio_name', $name);

        $this->replyWithMessage([
            'text' => 'Выберите тип портфеля:',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => 'Публичный', 'callback_data' => 'portfolio_type_public'],
                        ['text' => 'Приватный', 'callback_data' => 'portfolio_type_private'],
                    ],
                ],
            ]),
        ]);
    }

    public function createPortfolio($type)
    {
        $name = Session::get('portfolio_name');
        $userData = $this->getUpdate()->message->from;
        $userId = $userData->id;

        if($type === "Приватный"){
            $typeBool = 1;
        }
        else{
            $typeBool = 0;
        }

        $this->portfolio->insert([
            'telegram_user_id' => $userId,
            'name' => $name ?? null,
            'is_private' => $typeBool,
        ]);

        $this->replyWithMessage([
            'text' => "Портфель '{$name}' был успешно создан как '{$type}'.",
        ]);
    }
}
