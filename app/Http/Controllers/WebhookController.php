<?php

namespace App\Http\Controllers;

use App\Commands\CreatePortfolioCommand;
use App\Models\Portfolio;
use App\Models\TelegramUser;
use App\Models\UserState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;
use Telegram\Bot\BotsManager;

class WebhookController extends Controller
{
    protected BotsManager $botsManager;
    protected TelegramUser $telegramUser;
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

        $updateData = $request->all();
        $update = new \Telegram\Bot\Objects\Update($updateData);
        $this->botsManager->bot()->commandsHandler(true);
        $userId = $update->getChat()->id;
        if ($update->isType('callback_query')) {
            $callbackData = $update->getCallbackQuery()->getData();
            switch ($callbackData) {
                case '/create_portfolio':
                    $this->botsManager->bot()->sendMessage([
                        'chat_id' => $userId ,
                        'text' => 'Введите имя вашего портфеля:',
                        'parse_mode' => 'markdown',
                    ]);
                    $userState = new UserState();
                    $userState->telegram_user_id = $userId;
                    $userState->step = 'portfolio_name';
                    $userState->value = 'awaiting_portfolio_name';
                    $userState->save();
                    break;
                case '/search_portfolio':
                    $this->botsManager->bot()->sendMessage([
                        'chat_id' => $userId,
                        'text' => 'Введите username владельца портфеля:',
                        'parse_mode' => 'markdown',
                    ]);
                    break;
                case '/portfolio_type_public':
                case '/portfolio_type_private':
                    // Передаем 0 или 1 в зависимости от типа портфеля
                    $this->createPortfolio($callbackData === '/portfolio_type_private' ? 1 : 0, $userId);
                    $this->botsManager->bot()->sendMessage([
                        'chat_id' => $update->getChat()->id,
                        'text' => 'Портфель успешно создан',
                        'parse_mode' => 'markdown',
                    ]);
                    break;
                default:
                    break;
            }
        } elseif ($update->isType('message')) {
            $userState = UserState::where('telegram_user_id', $userId)->orderBy('created_at', 'desc')
                ->first();
            if ( $userState && $userState->value === 'awaiting_portfolio_name') {
                $portfolioName = $update->getMessage()->getText();
                $userState->value = $portfolioName;
                $userState->save();
                $this->botsManager->bot()->sendMessage([
                    'chat_id' =>  $userId,
                    'text' => "Вы ввели имя портфеля: *{$portfolioName}*.\nТеперь выберите тип портфеля:",
                    'parse_mode' => 'markdown',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => 'Публичный', 'callback_data' => '/portfolio_type_public'],
                                ['text' => 'Приватный', 'callback_data' => '/portfolio_type_private'],
                            ],
                        ],
                    ]),
                ]);
            }
        }
        return response(null, 200);
    }
    public function createPortfolio($type, $userId)
    {
        $userState = UserState::where('telegram_user_id', $userId)->orderBy('created_at', 'desc')->first();
        $name = $userState->value;
        $portfolio = new Portfolio();
        $portfolio->telegram_user_id = $userId;
        $portfolio->name = $name;
        $portfolio->is_private = $type;
        $portfolio->save();
    }
}
