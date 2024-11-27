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
                    $this->handleBotState($userId, 'portfolio_name','awaiting_portfolio_name');
                    $this->sendMessage($userId, 'Введите имя вашего портфеля:');
                    break;
                case '/search_portfolio':
                    $this->handleBotState($userId, 'search_portfolio','awaiting_username');
                    $this->sendMessage($userId, 'Введите username владельца без "@":');
                    break;

                case '/portfolio_type_public':
                case '/portfolio_type_private':
                    // Передаем 0 или 1 в зависимости от типа портфеля
                    $userState = UserState::where('telegram_user_id', $userId)->first();
                    $name = $userState->value;
                    $portfolio = Portfolio::where('telegram_user_id', $userId)->where('name', $name)->first();
                    $this->createPortfolio($callbackData === '/portfolio_type_private' ? 1 : 0, $userId, $name, $portfolio);
                    $this->showPortfolio($portfolio->id, $userId);
                    break;

                case '/main_menu':
                    $markup = ['inline_keyboard' => [
                        [
                            ['text' => 'Создать портфель', 'callback_data' => '/create_portfolio'],
                            ['text' => 'Поиск портфеля', 'callback_data' => '/search_portfolio']
                        ],
                    ]];
                    $this->sendMessage($userId, "Выберите действие:", $markup);
                    break;

                case preg_match('/^\/portfolio_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                    $portfolioId = $matches[1];
                    $this->showPortfolio($portfolioId, $userId);
                    break;
                case preg_match('/^\/add_token_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                    $portfolioId = $matches[1];
                    //введите сеть, кнопки
                   // $this->addToken($portfolioId);
                    break;
                default:
                    break;
            }
        } elseif ($update->isType('message')) {
            $userState = UserState::where('telegram_user_id', $userId)->first();
            //создание портфеля
            if ( $userState && $userState->value === 'awaiting_portfolio_name') {
                $portfolioName = $update->getMessage()->getText();
                $this->handleBotState($userId, $step = null ,$portfolioName);
                $markup = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Публичный', 'callback_data' => '/portfolio_type_public'],
                            ['text' => 'Приватный', 'callback_data' => '/portfolio_type_private'],
                        ]
                    ]];
                $this->sendMessage($userId, "Вы ввели имя портфеля: *{$portfolioName}*.\nТеперь выберите тип портфеля:", $markup);
            }
            //поиск портфеля по юзернейму
            elseif ($userState && $userState->value === 'awaiting_username'){
                $username = $update->getMessage()->getText();
                $user = TelegramUser::where('username', $username)->first();
                if (!$user) {
                    $markup = [
                        'inline_keyboard' => [
                            [
                                ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                            ],
                        ],
                    ];
                    $this->sendMessage($userId, "Пользователь с username @{$username} не найден.", $markup);
                    return response(null, 200);
                }

                $portfolio = $user->portfolio()->get();
                $this->handleBotState($userId, 'choose_portfolio',$username);
                if(count($portfolio)){
                    $text = "У @{$user->username} найдено:";
                    $premarkup = [];
                    foreach ($portfolio as $item) {
                        $premarkup[] = [[
                            "text" => $item->name,
                            "callback_data" => '/portfolio_' . $item->id,
                        ]];
                    }
                    $markup = [
                        'inline_keyboard' => $premarkup
                    ];
                }
                else{
                    $text = "У @{$user->username} не найдено доступных портфелей";

                    $markup = [
                        'inline_keyboard' => [
                            [
                                ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                            ],
                        ],
                    ];
                }
                $this->sendMessage($userId,  $text, $markup);
            }
        }
        return response(null, 200);
    }
    public function createPortfolio($type, $userId, $name, $portfolio)
    {
        if ($portfolio) {
            $portfolio->is_private = $type;
            $portfolio->save();
        } else {
            $portfolio = new Portfolio();
            $portfolio->telegram_user_id = $userId;
            $portfolio->name = $name;
            $portfolio->is_private = $type;
            $portfolio->save();
        }
    }

    private function sendMessage($chatId, $text, $markup = null): void
    {
        $messageData = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($markup) {
            $messageData['reply_markup'] = json_encode($markup);
        }

        $this->botsManager->bot()->sendMessage($messageData);
    }
    private function handleBotState($userID, $step, $value): void
    {
        $userState = UserState::where('telegram_user_id', $userID)->first();
        if ($userState) {
            if ($step !== null) {
                $userState->step = $step;
            }
            $userState->value = $value;
            $userState->save();
        } else {
            $newUserState = new UserState();
            $newUserState->telegram_user_id = $userID;
            if ($step !== null) {
                $newUserState->step = $step;
            }
            $newUserState->value = $value;
            $newUserState->save();
        }
    }
    private function showPortfolio($portfolioID, $userId)
    {

        $portfolioInfo = Portfolio::find($portfolioID);
        $author = $portfolioInfo->author()->get();
        $total = 1000;
        $tokensAmount = 2;
        //ToDo: формула баланса, PNL
        if ($portfolioInfo) {
            $msg = "{$portfolioInfo->name}: @{$author[0]->username}
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
                            \nADS: buy me buy buy buy buy";
            $markup =[
                'inline_keyboard' => [
                    [['text' => 'Обновить PNL', 'callback_data' => '/pnl'],
                        ['text' => 'В главное меню', 'callback_data' => '/main_menu']],
                    [['text' => 'Показать все активы', 'callback_data' => '/show_all']],
                    [['text' => 'Купить/добавить транзакцию', 'callback_data' => "/add_token_$portfolioID"]]
                ],
            ];
            //toDo: buttons
            $this->sendMessage($userId, $msg, $markup);
        } else {
            $markup = ['inline_keyboard' => [
                [
                    ['text' => 'Назад', 'callback_data' => '/step_back'],
                    ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                ],
            ]];
            $this->sendMessage($userId, "Портфолио не найдено.", $markup);
        }
    }
}
