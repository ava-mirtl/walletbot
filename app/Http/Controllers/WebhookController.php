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
                    $userState = new UserState();
                    $userState->telegram_user_id = $userId;
                    $userState->step = 'portfolio_name';
                    $userState->value = 'awaiting_portfolio_name';
                    $userState->save();
                    $this->botsManager->bot()->sendMessage([
                        'chat_id' => $userId ,
                        'text' => 'Введите имя вашего портфеля:',
                        'parse_mode' => 'markdown',
                    ]);
                    break;
                case '/search_portfolio':
                    $userState = new UserState();
                    $userState->telegram_user_id = $userId;
                    $userState->step = 'search_portfolio';
                    $userState->value = 'awaiting_username';
                    $userState->save();
                    $this->botsManager->bot()->sendMessage([
                        'chat_id' => $userId,
                        'text' => 'Введите username владельца без "@":',
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
                case preg_match('/^\/portfolio_(\d+)$/', $callbackData, $matches) ? $matches[0] : false: // Используем регулярное выражение для получения ID
                    $portfolioId = $matches[1];
                    $portfolioInfo = Portfolio::find($portfolioId);
                    $author = $portfolioInfo->author()->get();

                    $total = 1000;
                    $tokensAmount = 2;
                    //ToDo: формула баланса, PNL
                    if ($portfolioInfo) {
                        $this->botsManager->bot()->sendMessage([
                            'chat_id' => $userId,
                            'text' => "{$portfolioInfo->name}: @{$author[0]->username}
                            \nОбщий баланс: {$total}$ (gg 0.5| kek 0.2)
                            \nPNL: DAY 12% | WEEK 2%
                            \nMONTH 15% | ALL TIME 53%
                            \n\nАктивы: {$tokensAmount}/30 активных токенов
                            \n\n 1. Paal AI | 6524 \$PAAL ~ 2245$ | ETH | Price: 1.292$ AOV: 1.09$ | MCAP 700K | 60% value
                            \n2. NAMOTA AI | 524 \$NAI ~ 945$ | SOL | Price: 1$
                            \n\nBag Achivments: x2, x3, x4, x10
                            \nToken Achivments: x2 (3), x3 (5), x100 (1)
                            \n\n Страница 1/3
                            \n\n Последнее обновление: 13 November 13:05 UTC
                            \n\n-------------------------------------------------
                            \nADS: buy me buy buy buy buy",
                        ]);
                    } else {
                        $this->botsManager->bot()->sendMessage([
                            'chat_id' => $userId,
                            'text' => "Портфолио не найдено.",
                            'parse_mode' => 'markdown',
                        ]);
                    }
                    break;
                default:
                    break;
            }
        } elseif ($update->isType('message')) {
            $userState = UserState::where('telegram_user_id', $userId)->orderBy('created_at', 'desc')
                ->first();
            //создание портфеля
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
            //поиск портфеля по юзернейму
            elseif ($userState && $userState->value === 'awaiting_username'){
                $username = $update->getMessage()->getText();
                $user = TelegramUser::where('username', $username)->first();
                if (!$user) {
                    $this->botsManager->bot()->sendMessage([
                        'chat_id' => $userId,
                        'text' => "Пользователь с username @{$username} не найден.",
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Назад', 'callback_data' => '/step_back'],
                                    ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                                ],
                            ],
                        ]),
                    ]);
                    return response(null, 200);
                }
                $portfolio = $user->portfolio()->get();
                $userState->step = 'choose_portfolio';
                $userState->value = $username;
                $userState->save();
                if(count($portfolio)){
                    $text = "У @{$user->username} найдено:";
                    $markup = [];
                    foreach ($portfolio as $item) {
                        $markup[] = [[
                            "text" => $item->name,
                            "callback_data" => '/portfolio_' . $item->id,
                        ]];
                    }
                }
                else{
                    $text = "У @{$user->username} не найдено доступных портфелей";
                    $markup = [
                        ['text' => 'Назад', 'callback_data' => '/step_back'],
                        ['text' => 'В главное меню', 'callback_data' => '/main_menu']];
                }
//                dd([
//                    'chat_id' =>  $userId,
//                    'text' => $text,
//                    'parse_mode' => 'markdown',
//                    'reply_markup' => json_encode([
//                        'inline_keyboard' => $markup
//                    ]),
//                ]);
                $this->botsManager->bot()->sendMessage([
                    'chat_id' =>  $userId,
                    'text' => $text,
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $markup
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
