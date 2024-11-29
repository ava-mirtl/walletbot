<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Portfolio;
use App\Models\PortfolioContract;
use App\Models\TelegramUser;
use App\Models\UserState;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
        $userId = $update->getChat()->id;
        $this->botsManager->bot()->commandsHandler(true);

      if ($update->isType('callback_query')) {
            $callbackData = $update->getCallbackQuery()->getData();
            switch ($callbackData) {
                case '/create_portfolio':
                    $markup = [
                        'inline_keyboard' => [
                            [['text' => 'Публичный (без возможности редактирования)', 'callback_data' => '/portfolio_type_public']],
                            [['text' => 'Приватный (можно редактировать)', 'callback_data' => '/portfolio_type_private'],]
                        ]];

                    $this->sendMessage($userId, 'Выберите тип портфеля:', $markup);
                    break;
                case '/search_portfolio':
                    $this->handleBotState($userId, 'search_portfolio','awaiting_username');
                    $this->sendMessage($userId, 'Введите username владельца без "@":');
                    break;

                case '/portfolio_type_public':
                case '/portfolio_type_private':

                $this->handleBotState($userId, 'awaiting_portfolio_name',$callbackData === '/portfolio_type_public' ? 1 : 0);
                $this->sendMessage($userId, 'Введите имя портфеля');

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
                    $markup = [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Создать портфель', 'callback_data' => '/create_portfolio'],
                                ['text' => 'Поиск портфеля', 'callback_data' => '/search_portfolio']

                            ],
                            [
                                ['text' => 'Мои портфели', 'callback_data' => '/choose_type'],
                                ['text' => 'Настройки', 'callback_data' => '/settings']
                            ]
                        ]];
                    $this->sendMessage($userId, "Выберите действие:", $markup);
                    break;
//creating token in DB
                case '/create_token':
                    $txt = $this->createToken($userId);

                    $markup = ['inline_keyboard' => [
                        [
                            ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                        ],
                    ]];
                    $this->sendMessage($userId, $txt, $markup);
                    break;
              case preg_match('/^\/foreign_portfolio_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                  $portfolioId = $matches[1];
                  $this->showPortfolio($portfolioId, $userId, true);
                  break;

              case preg_match('/^\/portfolio_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                  $portfolioId = $matches[1];
                  $this->showPortfolio($portfolioId, $userId, false);
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
              case '/choose_type':
                  $markup = [
                      'inline_keyboard' => [
                          [
                              ['text' => 'Публичный', 'callback_data' => '/choose_public'],
                              ['text' => 'Приватный', 'callback_data' => '/choose_private'],
                          ],
                      ]];
                  $this->sendMessage($userId, 'Выберите тип портфеля:', $markup);
                  break;

              case '/choose_public':
              case '/choose_private':
                  $portfolios = Portfolio::where('telegram_user_id', $userId)->where('is_public', $callbackData === '/choose_public' ? 1 : 0)->get();
                  $buttons = [];
                  foreach ($portfolios as $portfolio) {
                      $buttons[] = [
                          'text' => $portfolio->name,
                          'callback_data' => "/portfolio_{$portfolio->id}"
                      ];
                  }
                  $buttons[] = ['text' => 'Назад', 'callback_data' => '/choose_type'];
                  $markup = [
                      'inline_keyboard' => array_chunk($buttons, 1)
                  ];

                  $this->sendMessage($userId, 'Выберите портфель:', $markup);
              break;
//settings block
                case '/settings';
                    $markup = [
                        'inline_keyboard' => [
                            [
                                ['text' => 'В настройки', 'callback_data' => '/settings_private'],
                            ],
                            [
                                ['text' => 'Назад', 'callback_data' => '/main_menu'],

                            ],
                        ]];

                    $this->sendMessage($userId, 'Установить настройки приватности:', $markup);
                    break;
              case '/settings_private';
                    $portfolios = Portfolio::where('telegram_user_id', $userId)->get();
                    $buttons = [];
                    foreach ($portfolios as $portfolio) {
                        $buttons[] = [
                            'text' => $portfolio->name,
                            'callback_data' => "/settings_portfolio_{$portfolio->id}"
                        ];
                    }
                    $buttons[] = ['text' => 'Назад', 'callback_data' => '/settings'];
                    $markup = [
                        'inline_keyboard' => array_chunk($buttons, 1)
                    ];

                    $this->sendMessage($userId, 'Выберите портфель:', $markup);
                  break;
                case preg_match('/^\/settings_portfolio_(\d+)$/', $callbackData, $matches) ? $matches[0] : false:
                    $portfolioID = $matches[1];
                    $markup = [
                        'inline_keyboard' => [
                            [['text' => "Сделать портфель публичным", 'callback_data' => "/set_public_$portfolioID"]],
                            [['text' => "Показывать достижения", 'callback_data' => "/show_achievements_$portfolioID"]],
                            [['text' => "Показывать ROI", 'callback_data' => "/show_roi_$portfolioID"]],
                            [['text' => "Показывать мои активы", 'callback_data' => "/show_activities_$portfolioID"]],
                            [['text' => "Показывать количество активов, стоимость", 'callback_data' => "/show_prices_$portfolioID"]],
                            [['text' => "Назад", 'callback_data' => "/settings_private"]],
                        ]
                    ];

                    $this->sendMessage($userId, "Настройки приватности портфеля", $markup);
                    break;

              case preg_match('/^\/set_public_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $portfolioID = $matches[1];
                  $this->handlePortfolioSettings($portfolioID, 'is_public', $userId, 'сделать публичным');
                  break;

              case preg_match('/^\/show_achievements_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $portfolioID = $matches[1];
                  $this->handlePortfolioSettings($portfolioID, 'is_achievements_shown', $userId, 'показать достижения');
                  break;

              case preg_match('/^\/show_roi_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $portfolioID = $matches[1];
                  $this->handlePortfolioSettings($portfolioID, 'is_roi_shown', $userId, 'показать ROI');
                  break;

              case preg_match('/^\/show_activities_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $portfolioID = $matches[1];
                  $this->handlePortfolioSettings($portfolioID, 'is_activities_shown', $userId, 'показать активы');
                  break;

              case preg_match('/^\/show_prices_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $portfolioID = $matches[1];
                  $this->handlePortfolioSettings($portfolioID, 'is_prices_shown', $userId, 'показать количество и стоимость активов');
                  break;
              case preg_match('/^\/find_token_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $portfolioID = $matches[1];
                  $this->handleBotState($userId, 'awaiting_token_index', $portfolioID);
                  $this->sendMessage($userId, "Введите номер актива");

                  break;
              default:
                    break;
            }
        } elseif ($update->isType('message')) {
            $userState = UserState::where('telegram_user_id', $userId)->first();
//создание портфеля
            if ( $userState && $userState->step === 'awaiting_portfolio_name') {
                $name = $update->getMessage()->getText();
                $type = $userState->value;
                $portfolio = Portfolio::where('telegram_user_id', $userId)->where('name', $name)->first();
                $this->createPortfolio($type, $userId, $name, $portfolio);
                $userState->delete();
                $this->showPortfolio($portfolio->id, $userId, false);
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
                    $userState->delete();
                    return response(null, 200);
                }

                $portfolio = $user->portfolio()->where('is_public', true)->get();
                $this->handleBotState($userId, 'choose_portfolio', $username);

                if (count($portfolio)) {
                    $text = "У @{$user->username} доступны портфели:";
                    $premarkup = [];
                    foreach ($portfolio as $item) {
                        $premarkup[] = [[
                            "text" => $item->name,
                            "callback_data" => '/foreign_portfolio_'.$item->id,
                        ]];
                    }
                    $premarkup[] = [[
                        "text" => "Назад",
                        "callback_data" => '/search_portfolio',
                    ]];
                    $markup = [
                        'inline_keyboard' => $premarkup
                    ];
                } else {
                    $text = "У @{$user->username} не найдено доступных публичных портфелей";

                    $markup = [
                        'inline_keyboard' => [
                            [
                                ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                            ],
                        ],
                    ];
                }
                $this->sendMessage($userId, $text, $markup);
            }
 //token+
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
//показать токен по номеру
          elseif ($userState && $userState->step === 'awaiting_token_index'){
                $i = $update->getMessage()->getText();
                $index = $i - 1;
                $tokens = Portfolio::find($userState->value)->tokens;
              if (isset($tokens[$index])) {
                  $token = $tokens[$index];
                  $this->showToken($token, $userState->value, $userId);
              } else {
                  $this->sendMessage($userId, "Токен с номером {$i} не найден.");
              }
          }
            elseif ($update->getMessage()->getText() ==='/start'){
                //чтобы не присылало дефолтное сообщение на команду
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
            $portfolio->is_public = $type;
            $portfolio->save();
        } else {
            $portfolio = new Portfolio();
            $portfolio->telegram_user_id = $userId;
            $portfolio->name = $name;
            $portfolio->is_public = $type;
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
    private function showPortfolio($portfolioID, $userId, $isForeign)
    {
        //ToDo: формула баланса, PNL
        $portfolioInfo = Portfolio::findOrFail($portfolioID);
        $author = $portfolioInfo->author;
        $tokens = $portfolioInfo->tokens;
        $total = 1000;
        $tokensAmount = count($tokens);


        $msg = "{$portfolioInfo->name}: @{$author->username}
               \nОбщий баланс: {$total}$ (gg 0.5| kek 0.2)";

        if ($portfolioInfo->is_roi_shown) {
            $msg .= "\nPNL: DAY 12% | WEEK 2% | MONTH 15% | ALL TIME 53%";
        }


        if ($portfolioInfo->is_activities_shown) {
            $msg .= "\n\nАктивы: {$tokensAmount}/30 активных токенов";

            foreach ($tokens as $token) {
                $tokenDetails = json_decode($token->api_response, true);
                if (isset($tokenDetails['data']) && count($tokenDetails['data']) > 0) {
                    $tokenInfo = $tokenDetails['data'][0]['attributes'];

                    $name = $tokenInfo['name'];
                    $symbol = $tokenInfo['symbol'];
                    $price = $tokenInfo['price_usd'];
                    $marketCap = $tokenInfo['market_cap_usd'];

                    $msg .= "\n- {$name} | {$symbol}";
                        if($portfolioInfo->is_prices_shown){
                            $msg .="|Price: \${$price} | Market Cap: \${$marketCap}";
                        }
                }
            }
        }
        if ($portfolioInfo->is_achievements_shown) {
            $msg .= "\nBag Achievements: x2, x3, x4, x10
        \nToken Achievements: x2 (3), x3 (5), x100 (1)";
        }
        $msg .= "\n\nСтраница 1/3
                 \n\nПоследнее обновление: 13 November 13:05 UTC
                 \n\n-------------------------------------------------
                 \nADS: buy me buy buy buy buy";
        if($isForeign){
            $markup = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Обновить PNL', 'callback_data' => '/pnl'],
                        ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                    ],
                    [
                        ['text' => 'Назад', 'callback_data' => '/']
                    ],
                    [
                        ['text' => 'Вперед', 'callback_data' => "/"]
                    ]
                ],
            ];
        }
        else{
            $markup = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Обновить PNL', 'callback_data' => '/pnl'],
                        ['text' => 'В главное меню', 'callback_data' => '/main_menu']
                    ],
                    [
                        ['text' => 'Показать все активы', 'callback_data' => '/show_all']
                    ],
                    [
                        ['text' => 'Купить/добавить транзакцию', 'callback_data' => "/add_token_$portfolioID"]
                    ],
                    [
                        ['text' => 'Перейти в актив', 'callback_data' => "/find_token_$portfolioID"]
                    ],
                    [
                        ['text' => 'Назад', 'callback_data' => '/']
                    ],
                    [
                        ['text' => 'Вперед', 'callback_data' => "/"]
                    ]
                ],
            ];
        }


            $this->sendMessage($userId, $msg, $markup);


    }
    protected function showToken($token, $portfolioID, $userId){

        $txt = "Статистика по токену $token->symbol - $token->name:
        CA: $token->address
        Баланс: 3412$/6524 $token->symbol (1.1 $token->network)

        MCAP 192M | 70% value
         Current price: 1.292 $ | AOV: 1.09$
        PNL: DAY: 17% | WEEK: 2% | MONTH: 23% | ALL TIME: 53%

        История транзакций:
        Buy: 24 июня 1234 $token->symbol/12$ (цена покупки 0.003$)
        Buy: 28 июня 255 $token->symbol/100$ (цена покупки 0.025$)
        Sell: 17 августа 1500 $token->symbol/4444$ (цена продажи 0.028)

        Token Achivements: X2, X5, X10";

       $markup = [
            'inline_keyboard' => [

                [['text' => "Купить $token->symbol", 'callback_data' => "/buy_token_$token->id"],
                ['text' => "Продать $token->symbol", 'callback_data' => "/sell_token_$token->id"]],
                [['text' => "В портфель", 'callback_data' => "/portfolio_$portfolioID"],
                 ['text' => "Обновить PNL", 'callback_data' => "/reload_$token->id"]],
            ]
        ];
        $this->sendMessage($userId, $txt, $markup);
    }
    protected function createToken($userId)
    {
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
                    return 'Токен сохранен';
                } else {
                    return 'Не удалось добавить токен: отсутствует ID портфолио.';
                }
            } else {
                throw new \Exception('Ошибка API: данные токена отсутствуют.');
            }
        } catch (\Exception $e) {
            return 'Не удалось создать токен: ' . $e->getMessage();
        }
    }
    public function handlePortfolioSettings($portfolioID, $attribute, $userId, $text_attribute) {
        $markup = [
            'inline_keyboard' => [
                [['text' => "Назад", 'callback_data' => "/settings_portfolio_$portfolioID"]],
            ]
        ];
                try {
                    DB::transaction(function () use ($portfolioID, $attribute, ) {
                        $portfolio = Portfolio::find($portfolioID);

                        if ($portfolio) {
                            $portfolio->$attribute = 1;
                            $portfolio->save();
                        } else {
                            throw new \Exception('Портфель не найден.');
                        }
                    });
                    $this->sendMessage($userId, "Настройки свойства \"$text_attribute\" применились", $markup);
                } catch (QueryException $e) {
                    $this->sendMessage($userId, "Ошибка при обновлении портфеля: " . $e->getMessage(), $markup);
                } catch (\Exception $e) {
                    $this->sendMessage($userId, "Произошла ошибка: " . $e->getMessage(), $markup);
                }
            }

}
