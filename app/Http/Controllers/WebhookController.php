<?php

namespace App\Http\Controllers;

use App\Commands\CreatePortfolioCommand;
use App\Models\Contract;
use App\Models\Portfolio;
use App\Models\PortfolioContract;
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
                case 'eth':
                case 'sol':
                case 'bsc':
                case 'trx':
                case 'base':
                case 'ton':
                $userState = UserState::where('telegram_user_id', $userId)->first();
                $portfolioID = $userState->value;
                $data = json_encode([
                    ['telegram_user_id' => $userId],
                    ['portfolio_id' =>  $portfolioID],
                    ['network' => $callbackData]
                ]);
                $this->handleBotState($userId, 'awaiting_token_address', $data);
                $this->sendMessage($userId, 'Введите адрес операции:');
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
                    //creating token in DB
                case '/create_token':
                    $userState = UserState::where('telegram_user_id', $userId)->first();
                    $tokenData = json_decode($userState->value);

                    // Подготовка данных для токена
                    $tokenAddress = $tokenData[3]->address; // Получаем адрес токена

                    $token = new Contract();
                    $token->network = $tokenData[2]->network;
                    $token->address = $tokenAddress;
                    $token->tax = $tokenData[4]->tax;
                    $token->quantity = $tokenData[5]->amount;

                    try {
                        $client = new \GuzzleHttp\Client();
                        $response = $client->request('GET', 'https://api.geckoterminal.com/api/v2/networks/'.$tokenData[2]->network.'/tokens/multi/'.$tokenAddress);
                        $apiResponse = json_decode($response->getBody());

                        if (isset($apiResponse->data)) {
                            $token->api_response = json_encode($apiResponse);
                            $token->save();

                            if (isset($tokenData[1]->portfolio_id)) {
                                $portfolioContract = new PortfolioContract();
                                $portfolioContract->portfolio_id = $tokenData[1]->portfolio_id;
                                $portfolioContract->contract_id = $token->id;
                                $portfolioContract->save();
                                $txt = 'Токен сохранен';
                            } else {
                                $txt = 'Не удалось добавить токен: отсутствует ID портфолио.';
                            }
                        } else {
                            throw new \Exception('Ошибка API: данные токена отсутствуют.');
                        }
                    } catch (\Exception $e) {
                        $txt = 'Не удалось создать токен: ' . $e->getMessage();
                    }

                    $markup = ['inline_keyboard' => [
                        [
                            ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                        ],
                    ]];
                    $this->sendMessage($userId, $txt, $markup);
                    break;
                case preg_match('/^\/portfolio_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                    $portfolioId = $matches[1];
                    $this->showPortfolio($portfolioId, $userId);
                    break;
                case preg_match('/^\/add_token_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                    $portfolioId = $matches[1];
                    $this->handleBotState($userId, 'awaiting_token_network', $portfolioId);
                    $markup = [
                        'inline_keyboard' => [
                            [
                                ['text' => 'ETH', 'callback_data' => 'eth'],
                                ['text' => 'SOL', 'callback_data' => 'sol'],
                                ['text' => 'BSC', 'callback_data' => 'bsc']
                            ],
                            [
                                ['text' => 'TRX', 'callback_data' => 'trx'],
                                ['text' => 'BASE', 'callback_data' => 'base'],
                                ['text' => 'TON', 'callback_data' => 'ton'],
                            ],
                        ]];
                    $this->sendMessage($userId, 'Выберите сеть:', $markup);
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
            elseif($userState && $userState->step === 'awaiting_token_address'){
                $data = json_decode($userState->value);

                $data[3] = ['address' => $update->getMessage()->getText()];

                $jsondata = json_encode($data);
                $this->handleBotState($userId, 'awaiting_tax', $jsondata);
                $this->sendMessage($userId, 'Введите сколько процентов составил налог (только цифры):');
            }
            elseif($userState && $userState->step === 'awaiting_tax'){
                $data = json_decode($userState->value);
                $data[4] = ['tax' => $update->getMessage()->getText()];
                $jsondata = json_encode($data);
                $this->handleBotState($userId, 'awaiting_amount', $jsondata);
                $this->sendMessage($userId, 'Введите количество токенов (только цифры):');
            }
            elseif($userState && $userState->step === 'awaiting_amount'){
                $data = json_decode($userState->value);
                $amount = $update->getMessage()->getText();
                $network = $data[2]->network;
                $tax = $data[4]->tax;
                $data[5] = ['amount' => $amount];
                $markup = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Нет', 'callback_data' => '/main_menu'],
                            ['text' => 'Да', 'callback_data' => '/create_token']

                        ]]];
                $text = "Подтвердите правильность транзакции: \n\n
                Buy: $amount $network Tax: $tax%";
                $jsondata = json_encode($data);

                $this->handleBotState($userId, 'awaiting_agree', $jsondata);
                $this->sendMessage($userId, $text, $markup);
            }
            else{
                $text = "Прошу прощения, непонятная команда. Я еще маленький бот и не умею распозновать все входящие запросы. Вы можете вернуться в меню и попробовать еще раз.";
                $markup = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                        ],
                    ],
                ];
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
        //ToDo: формула баланса, PNL
        $portfolioInfo = Portfolio::findOrFail($portfolioID);
        $author = $portfolioInfo->author;
        $tokens = $portfolioInfo->tokens;
        $total = 1000;
        $tokensAmount = count($tokens);

        $msg = "{$portfolioInfo->name}: @{$author->username}
            \nОбщий баланс: {$total}$ (gg 0.5| kek 0.2)
            \nPNL: DAY 12% | WEEK 2%
            \nMONTH 15% | ALL TIME 53%
            \n\nАктивы: {$tokensAmount}/30 активных токенов\n\n";

        foreach ($tokens as $token) {
            $tokenDetails = json_decode($token->api_response, true);

            if (isset($tokenDetails['data']) && count($tokenDetails['data']) > 0) {
                $tokenInfo = $tokenDetails['data'][0]['attributes'];

                $name = $tokenInfo['name'];
                $symbol = $tokenInfo['symbol'];
                $price = $tokenInfo['price_usd'];
                $marketCap = $tokenInfo['market_cap_usd'];

                $msg .= "- {$name} | {$symbol} | Price: \${$price} | Market Cap: \${$marketCap}\n";
            }
        }

        $msg .= "\n\nBag Achivments: x2, x3, x4, x10
          \nToken Achivments: x2 (3), x3 (5), x100 (1)
          \n\nСтраница 1/3
          \n\nПоследнее обновление: 13 November 13:05 UTC
          \n\n-------------------------------------------------
          \nADS: buy me buy buy buy buy";

        $markup = [
            'inline_keyboard' => [
                [['text' => 'Обновить PNL', 'callback_data' => '/pnl'],
                    ['text' => 'В главное меню', 'callback_data' => '/main_menu']],
                [['text' => 'Показать все активы', 'callback_data' => '/show_all']],
                [['text' => 'Купить/добавить транзакцию', 'callback_data' => "/add_token_$portfolioID"]]
            ],
        ];

            $this->sendMessage($userId, $msg, $markup);


    }

}
