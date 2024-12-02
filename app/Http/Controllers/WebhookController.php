<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Portfolio;
use App\Models\PortfolioContract;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\UserState;
use Exception;
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
                    $userState = UserState::where('telegram_user_id', $userId)->first();
                    $tokenData = json_decode($userState->value);
                    $portfolio_id = $tokenData[1]->portfolio_id;
                    $txt = $this->createToken($userId, $tokenData);
                    $markup = ['inline_keyboard' => [
                        [
                            ['text' => 'В портфель', 'callback_data' => "/portfolio_$portfolio_id"],
                            ['text' => 'В главное меню', 'callback_data' => "/main_menu"]
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
                case '/settings':
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
              case '/settings_private':
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
              case preg_match('/^\/buy_token_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $type = 'buy';
                  $portfolioID = UserState::where('telegram_user_id', $userId)->first()->value;
                  $data = [];
                  $data['token_id'] = $matches[1];
                  $data['type'] = $type;
                  $data['portfolio_id'] = $portfolioID;
                  $dataX = json_encode($data);
                  $this->handleBotState($userId, 'awaiting_token_quantity', $dataX);
                  $this->sendMessage($userId, "Введите количество токена");
                  break;

              case preg_match('/^\sell_token_(\d+)$/', $callbackData, $matches)? $matches[0] : false:
                  $type = 'sell';
                  $portfolioID = UserState::where('telegram_user_id', $userId)->first()->value;
                  $data = [];
                  $data['token_id'] = $matches[1];
                  $data['type'] = $type;
                  $data['portfolio_id'] = $portfolioID;
                  $dataX = json_encode($data);
                  $this->handleBotState($userId, 'awaiting_token_quantity', $dataX);
                  $this->sendMessage($userId, "Введите количество токена");
                  break;
              case '/disagree':
                  $userState = UserState::where('telegram_user_id', $userId)->first();
                  $existingData = json_decode($userState->value, true);
                  $portfolio_id =  $existingData['portfolio_id'];
                  $token = Contract::find($existingData['token_id']);
                  $this->showToken($token,$portfolio_id ,$userId);
                  break;
              case '/agree':
                  $userState = UserState::where('telegram_user_id', $userId)->first();
                  $existingData = json_decode($userState->value, true);
                  $type =  $existingData['type'];
                  $token = Contract::find($existingData['token_id']);
                  $portfolio_id =  $existingData['portfolio_id'];
                    if ($type === "buy"){
//toDo: add transaction, api method to calculate price, +=amount
                    }
                    elseif ($type === "sell"){
//toDo: add transaction, api method to calculate price, -=amount

                     }

                  $this->showToken($token,$portfolio_id ,$userId);
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

                $portfolio = $user->portfolio()->where('is_public', 1)->get();
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
          elseif ($userState && $userState->step === 'awaiting_amount') {
              $amount = $update->getMessage()->getText();
              $userInsert = json_decode($userState->value);
              $network = $userInsert[2]->network;
              $address = $userInsert[3]->address;
              $tax = $userInsert[4]->tax;
              $portfolio = $userInsert[1]->portfolio_id;
              try {
                  $client = new \GuzzleHttp\Client();
                  $response = $client->request('GET', 'https://api.geckoterminal.com/api/v2/networks/' . $network . '/tokens/multi/' . $address);
                  $apiResponse = json_decode($response->getBody());

                  if (isset($apiResponse->data) && count($apiResponse->data) > 0) {
                      $tokenAttributes = $apiResponse->data[0]->attributes;
                      $basePrice = $tokenAttributes->price_usd * $amount;
                      $taxAmount = ($basePrice * $tax) / 100;
                      $totalPrice = round($basePrice + $taxAmount, 1);

                      $text = "Подтвердите правильность транзакции:
                    \n\n Buy: $amount {$tokenAttributes->symbol} ~ {$totalPrice}$ (включая Tax: $tax%)";

                      $markup = [
                          'inline_keyboard' => [
                              [
                                  ['text' => 'Нет', 'callback_data' => '/main_menu'],
                                  ['text' => 'Да', 'callback_data' => '/create_token']
                              ]
                          ]
                      ];

                      $userInsert[5] = ['amount' => $amount];
                      $userInsert[6] = ['tokenAttributes' => $tokenAttributes];
                      $jsondata = json_encode($userInsert);
                      $this->handleBotState($userId, 'awaiting_agree', $jsondata);

                  } else {
                      $text = 'Ошибка API: данные токена отсутствуют.';
                      $markup = [
                          'inline_keyboard' => [
                              [
                                  ['text' => 'Назад', 'callback_data' => "/portfolio_$portfolio"],
                              ]
                          ]];
                  }
              } catch (\Exception $e) {
                  $text =  'Ошибка при получении данных токена: ' . $e->getMessage();
                  $markup = [
                      'inline_keyboard' => [
                          [
                              ['text' => 'Назад', 'callback_data' => '/main_menu'],
                          ]
                      ]];
              }
              $this->sendMessage($userId, $text, $markup);
          }

//показать токен по номеру
          elseif ($userState && $userState->step === 'awaiting_token_index'){
                $i = intval($update->getMessage()->getText());
                $index = $i - 1;
                $tokens = Portfolio::find($userState->value)->tokens;
              if (isset($tokens[$index])) {
                  $token = $tokens[$index];
                  $this->showToken($token, $userState->value, $userId);
              } else {
                  $this->sendMessage($userId, "Токен с номером {$i} не найден.");
              }
          }
          elseif ($userState && $userState->step === 'awaiting_token_quantity'){
              $amount = $update->getMessage()->getText();
              $existingData = json_decode($userState->value, true);
              $existingData['amount'] = $amount;
              $data = json_encode($existingData);
              $this->handleBotState($userId, 'awaiting_token_tax', $data);
              $this->sendMessage($userId, "Введите % налога");
          }
          elseif ($userState && $userState->step === 'awaiting_token_tax') {
              $tax = $update->getMessage()->getText();
              $existingData = json_decode($userState->value, true);
              $existingData['tax'] = $tax;
              $data = json_encode($existingData);
              $this->handleBotState($userId, 'awaiting_token_agreement', $data);

              $markup = [
                  'inline_keyboard' => [
                      [
                          ['text' => 'Нет', 'callback_data' => '/disagree'],
                          ['text' => 'Да', 'callback_data' => '/agree']
                      ]
                  ]
              ];
              $this->sendMessage($userId, 'Введите подтверждение налоговой ставки', $markup);
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
        //ToDo: формула баланса, PNL $total

        $portfolioInfo = Portfolio::findOrFail($portfolioID);
        $author = $portfolioInfo->author;
        $tokens = $portfolioInfo->tokens;
        $total = 1000;
        $tokensAmount = count($tokens);


        $msg = "{$portfolioInfo->name}: @{$author->username}
               \nОбщий баланс: {$total}$ (gg 0.5| kek 0.2)";

        if ($portfolioInfo->is_roi_shown||!$isForeign) {
            $msg .= "\nPNL: DAY 12% | WEEK 2% | MONTH 15% | ALL TIME 53%";
        }


        if ($portfolioInfo->is_activities_shown||!$isForeign) {
            $msg .= "\n\nАктивы: {$tokensAmount}/30 активных токенов";

            foreach ($tokens as $token) {


                    $name = $token->name;
                    $symbol = $token->symbol;
                    $price = $token->price_usd;
                    $network =  strtoupper($token->network);
                    $amount = $token->quantity;
                    $current_price = round($amount * $token->current_price);
                    $msg .= "\n- {$name} | $amount {$symbol} ~ $current_price$ | $network";
                        if($portfolioInfo->is_prices_shown||!$isForeign){
                            $msg .=" | Price: \${$price}";
                        }
                }
        }
        if ($portfolioInfo->is_achievements_shown||!$isForeign) {
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
        $transactions = Transaction::where('token_id', $token->id)->get();
        //toDo: метод обновления транзакции при добавлении или продаже токена, модель, метод в этой функции извлекающий инф о транзакции.

        $currentPrice = $token->current_price*$token->amount;
        $price = 0;
        foreach($transactions as $transaction){
            $price += $transaction->sum;
        }
        $mcap = $this->formatMarketCap($token->market_cap_usd);
        $aov = round($token->volume_usd_h24, 2);

        $txt = "Статистика по токену $token->symbol - $token->name:
        \nCA: $token->address
        \nБаланс: $price$|$token->quantity $token->symbol  (X $token->network)
        \nMCAP $mcap | 100% value
        \nCurrent price: $currentPrice $ | AOV: $aov$
        \nPNL: DAY: 17% | WEEK: 2% | MONTH: 23% | ALL TIME: 53%
        \n\nИстория транзакций: ";
            foreach($transactions as $transaction){
                $formattedDate = date('d F', strtotime($transaction->created_at));
                $txt .= "\n$transaction->type: $formattedDate $transaction->amount $token->symbol| $transaction->sum$ (цена покупки $transaction->price$)";
            }
        $txt .= "\n\nToken Achivements: X2, X5, X10";



       $markup = [
            'inline_keyboard' => [

                [['text' => "Купить $token->symbol", 'callback_data' => "/buy_token_$token->id"],
                ['text' => "Продать $token->symbol", 'callback_data' => "/sell_token_$token->id"]],
                [['text' => "В портфель", 'callback_data' => "/portfolio_$portfolioID"],
                 ['text' => "Обновить PNL", 'callback_data' => "/reload_$token->id"]],
            ]
        ];
        $this->sendMessage($userId, $txt, $markup);
       $state = UserState::where('telegram_user_id', $userId)->first();
       if ($state){
           $this->handleBotState($userId, 'portfolio_id', $portfolioID);
       }
    }
    protected function createToken($userId, $tokenData)
    {
        $tokenAddress = $tokenData[3]->address;
        $existingToken = Contract::where('address', $tokenAddress)->first();
        if ($existingToken) {
            return 'Токен с таким адресом уже существует: ' . $tokenAddress;
        }
        $token = new Contract();
        $token->network = $tokenData[2]->network;
        $token->address = $tokenAddress;
        $token->tax = $tokenData[4]->tax;
        $token->quantity = $tokenData[5]->amount;
        $token->name = $tokenData[6]->tokenAttributes->name;
        $token->symbol = $tokenData[6]->tokenAttributes->symbol;
        $token->price_usd = $tokenData[6]->tokenAttributes->price_usd ? (float)$tokenData[6]->tokenAttributes->price_usd : null;
        $token->current_price = $tokenData[6]->tokenAttributes->price_usd ? (float)$tokenData[6]->tokenAttributes->price_usd : null;
        $token->market_cap_usd = $tokenData[6]->tokenAttributes->market_cap_usd ? (float)$tokenData[6]->tokenAttributes->market_cap_usd : null;
        $token->fdv_usd = $tokenData[6]->tokenAttributes->fdv_usd ? (float)$tokenData[6]->tokenAttributes->fdv_usd : null;
        $token->total_supply = $tokenData[6]->tokenAttributes->total_supply ? (float)str_replace('.', '', $tokenData[6]->tokenAttributes->total_supply) : null;
        $token->total_reserve_in_usd = $tokenData[6]->tokenAttributes->total_reserve_in_usd ? (float)$tokenData[6]->tokenAttributes->total_reserve_in_usd : null;
        $token->volume_usd_h24 = $tokenData[6]->tokenAttributes->volume_usd->h24 ? (float)$tokenData[6]->tokenAttributes->volume_usd->h24 : null;
        try {
            $token->save();
        } catch (Exception $e) {
            return 'Не удалось сохранить токен: ' . $e->getMessage();
        }
        $transaction = new Transaction();
        $transaction->token_id = $token->id;
        $transaction->type = 'buy';
        $transaction->amount = $token->quantity;
        $basePrice = $token->price_usd * $token->quantity;
        $taxAmount = ($basePrice * $token->tax) / 100;
        $transaction->price = $token->price_usd;
        $transaction->sum = round($basePrice + $taxAmount, 1);
        try {
            $transaction->save();
        } catch (Exception $e) {
            return 'Не удалось сохранить транзакцию: ' . $e->getMessage();
        }
         if (isset($tokenData[1]->portfolio_id)) {
            $portfolioContract = new PortfolioContract();
            $portfolioContract->portfolio_id = $tokenData[1]->portfolio_id;
            $portfolioContract->contract_id = $token->id;
            $portfolioContract->save();
            $this->showToken($token, $tokenData[1]->portfolio_id, $tokenData[0]->user_id);
        } else {
            return 'Не удалось добавить токен: отсутствует ID портфолио.';
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
    function formatMarketCap($marketCap): string
    {
        if ($marketCap >= 1000000000) { // 1 миллиард
            return round($marketCap / 1000000000, 2) . 'B'; // Форматируем до миллиардов с двумя десятичными знаками
        } elseif ($marketCap >= 1000000) { // 1 миллион
            return round($marketCap / 1000000, 2) . 'M'; // Форматируем до миллионов с двумя десятичными знаками
        } elseif ($marketCap >= 1000) { // 1 тысяча
            return round($marketCap / 1000, 2) . 'K'; // Форматируем до тысяч с двумя десятичными знаками
        } else {
            return $marketCap;
        }
    }


}
