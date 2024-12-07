<?php

namespace App\Services;

use App\Models\Pnl;
use App\Models\Portfolio;
use App\Models\PortfolioPnl;
use App\Models\Token;
use Carbon\Carbon;

class PnlManager
{
    /**
     * Вычисляет PnL (прибыль и убыток) в процентах.
     *
     * @param float|int $current - текущая сумма
     * @param float|int $previous - предыдущая сумма
     * @return float|int - результат в процентах
     */
    public static function calculatePnl($current, $previous): float|int
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0; // Если предыдущая сумма 0, считаем PnL 100% роста или убытка от нуля
        }
        return round((($current - $previous) / $previous) * 100);
    }

    /**
     * Добавляет PnL для пользователей по различным временным категориям.
     *
     * @param int $tokenId - ID токена
     * @param string $network - сеть
     * @param float $coinPrice - цена монеты
     * @param float $amount - количество
     * @param float $totalInvest - общая инвестиция
     * @param float $profit - прибыль
     */
    public function tokenPnl($tokenId, $network, $coinPrice, $amount, $totalInvest, $profit)
    {
        $current = $amount * $coinPrice + $profit;
        $currentData = Pnl::where('token_id', $tokenId)->orderBy('created_at', 'desc')->get();

        $previousDayAgo = $totalInvest;
        $previousWeekAgo = $totalInvest;
        $previousMonthAgo = $totalInvest;
        $previousAllTime = $totalInvest;

        if ($currentData->isNotEmpty()) {
            foreach ($currentData as $pnl) {
                $createdAt = Carbon::parse($pnl->created_at);
                if ($createdAt->isYesterday() && $previousDayAgo == $totalInvest) {
                    $previousDayAgo = $pnl->current_price;
                }

                if ($createdAt->isBetween(Carbon::now()->subDays(8), Carbon::now()->subDays(1)) && $previousWeekAgo == $totalInvest) {
                    $previousWeekAgo = $pnl->current_price;
                }

                if ($createdAt->isLastMonth() && $previousMonthAgo == $totalInvest) {
                    $previousMonthAgo = $pnl->current_price;
                }

            }
        }

        $pnl = new Pnl();
        $pnl->token_id = $tokenId;
        $pnl->coin_price = $coinPrice;
        $pnl->network_price = $network;
        $pnl->day = self::calculatePnl($current, $previousDayAgo);
        $pnl->week = self::calculatePnl($current, $previousWeekAgo);
        $pnl->month = self::calculatePnl($current, $previousMonthAgo);
        $pnl->all_time = self::calculatePnl($current, $previousAllTime);
        $pnl->current_price = $current;
        $pnl->save();
    }
    /**
     * Добавляет PnL для пользователей по различным временным категориям.
     *
     * @param int $portfolioId - ID портфеля
     * @param float $currentPrice - цена монеты
     * @param float $totalInvest - общая инвестиция
     * @param float $profit - прибыль
     */
    public function portfolioPnl($portfolioId, $totalInvest, $coinPrice, $profit, $amount)
    {
        $currentData = PortfolioPnl::where('portfolio_id', $portfolioId)->orderBy('created_at', 'desc')->get();
        $current = $amount * $coinPrice + $profit;

        $previousDayAgo = $totalInvest;
        $previousWeekAgo = $totalInvest;
        $previousMonthAgo = $totalInvest;
        $previousAllTime = $totalInvest;

        if ($currentData->isNotEmpty()) {
            foreach ($currentData as $pnl) {
                $createdAt = Carbon::parse($pnl->created_at);

                if ($createdAt->isYesterday() && $previousDayAgo == $totalInvest) {
                    $previousDayAgo = $pnl->current_price;
                }

                if ($createdAt->isBetween(Carbon::now()->subDays(8), Carbon::now()->subDays(1)) && $previousWeekAgo == $totalInvest) {
                    $previousWeekAgo = $pnl->current_price;
                }

                if ($createdAt->isLastMonth() && $previousMonthAgo == $totalInvest) {
                    $previousMonthAgo = $pnl->current_price;
                }
            }
        }

        $portfolioPnl = new PortfolioPnl();
        $portfolioPnl->portfolio_id = $portfolioId;
        $portfolioPnl->current_price = $current;
        $portfolioPnl->day = self::calculatePnl($current, $previousDayAgo);
        $portfolioPnl->week = self::calculatePnl($current, $previousWeekAgo);
        $portfolioPnl->month = self::calculatePnl($current, $previousMonthAgo);
        $portfolioPnl->all_time = self::calculatePnl($current, $previousAllTime);
        $portfolioPnl->total_invest = $totalInvest;
        $portfolioPnl->save();
    }
    public function updatePortfolioPnl($portfolioID)
    {
        // Получаем кошелек
        $portfolio = Portfolio::with('tokens')->findOrFail($portfolioID);
        $tokens = $portfolio->tokens;
        $portfolioCurrentPrice = 0;

        // Группируем адреса токенов по сетям
        $tokenAddresses = [];
        $networkTokens = [];
        foreach ($tokens as $token) {
            $tokenAddresses[] = $token->address;
            $networkTokens[$token->network][] = $token;
        }

        // Получаем цены для всех токенов за один запрос
        $prices = $this->fetchTokenPrices($tokenAddresses, $tokens[0]->network); // Используем первую сеть для `network`

        // Получаем цены для всех сетей за один запрос
        $networkPrices = $this->fetchNetworkPrices(array_keys($networkTokens));
        foreach ($tokens as $token) {
            if (isset($prices[$token->address])) {
                $newPrice = $prices[$token->address];
                $newNetwPrice = $networkPrices[$token->network] ?? null;

                $portfolioCurrentPrice += $newPrice * $token->total_amount + $token->profit;
                $token->coin_price = $newPrice;
                $token->network_price = $newNetwPrice;
                $token->save();

                $this->tokenPnl($token->id, $newNetwPrice, $newPrice, $token->total_amount, $token->total_invest, 0);
            }
        }

        // Пересчитываем общий PNL для портфолио
        $this->recalculatePortfolioPnl($portfolio, $portfolioCurrentPrice);

        // Опционально: Вернуть статус обновления
        return true;
    }
    public function updateTokenPnl($tokenId)
    {
        $token = Token::find($tokenId);

        if (!$token) {
            return false;
        }

        $newCoinPrice = $this->getNewCoinPrice($token);
        $newNetworkPrice = $this->getNewNetworkPrice($token->network);

        // Рассчитываем текущую стоимость
        $current = ($token->total_amount * $newCoinPrice) + $token->profit;

        // Получаем последний PnL для расчета
        $lastPnl = Pnl::where('token_id', $tokenId)->orderBy('created_at', 'desc')->first();

        // Инициализация предыдущих значений
        $previousDayAgo = $lastPnl ? $lastPnl->current_price : 0;
        $previousWeekAgo = $lastPnl ? $lastPnl->current_price : 0;
        $previousMonthAgo = $lastPnl ? $lastPnl->current_price : 0;
        $previousAllTime = $lastPnl ? $lastPnl->current_price : 0;

        // Создаем новую запись PnL
        $pnl = new Pnl();
        $pnl->token_id = $tokenId;
        $pnl->coin_price = $newCoinPrice;
        $pnl->network_price = $newNetworkPrice;
        $pnl->current_price = $current;

        // Вычисляем PnL на основе предыдущих значений
        $pnl->day = self::calculatePnl($current, $previousDayAgo);
        $pnl->week = self::calculatePnl($current, $previousWeekAgo);
        $pnl->month = self::calculatePnl($current, $previousMonthAgo);
        $pnl->all_time = self::calculatePnl($current, $previousAllTime);

        $pnl->save();

        return true;
    }
    private function fetchTokenPrices(array $tokenAddresses, $network)
    {
        $client = new \GuzzleHttp\Client();
        $url = 'https://api.geckoterminal.com/api/v2/networks/' . $network . '/tokens/multi/' . implode(',', $tokenAddresses);
        $response = $client->request('GET', $url);
        $apiResponse = json_decode($response->getBody(), true);

        $prices = [];
        if (isset($apiResponse['data'])) {
            foreach ($apiResponse['data'] as $tokenData) {
                $prices[$tokenData['attributes']['address']] = $tokenData['attributes']['price_usd'];
            }
        }
        return $prices;
    }

    public function fetchNetworkPrices(array $networks)
    {
        $fullNetworkNames = [
            'eth' => 'ethereum',
            'solana' => 'solana',
            'bsc' => 'binancecoin',
            'ton' => 'ton',
            'trx' => 'tron',
            'base' => 'base'
        ];

        $ids = [];
        foreach ($networks as $network) {
            if (!isset($fullNetworkNames[$network])) {
                throw new Exception('Unsupported network');
            }
            // Добавляем id для запроса в API
            $ids[] = $fullNetworkNames[$network];
        }

        // Выполняем один запрос для всех сетей
        $url = "https://api.coingecko.com/api/v3/simple/price?ids=" . implode(',', $ids) . "&vs_currencies=usd";
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url);
        $apiResponse = json_decode($response->getBody(), true);

        // Определяем массив с ценами
        $networkPrices = [];

        // Теперь заполняем сети с использованием их кодов
        foreach ($networks as $network) {
            $networkPrices[$network] = $apiResponse[$fullNetworkNames[$network]]['usd'] ?? null;
        }
        return $networkPrices;
    }
    private function getNewCoinPrice($token)
    {
        $tokenAddresses = [$token->address];
        $prices = $this->fetchTokenPrices($tokenAddresses, $token->network);
        return $prices[$token->address] ?? null;
    }

    public function getNewNetworkPrice($network)
    {
        $networkPrices = $this->fetchNetworkPrices([$network]);
        return $networkPrices[$network] ?? null; // Вернет текущую цену сети в USD или null, если цена не найдена
    }
    protected function recalculatePortfolioPnl($portfolio, $currentPrice)
    {
        $currentData = PortfolioPnl::where('portfolio_id', $portfolio->id)->orderBy('created_at', 'desc')->get();
        if ($currentData->isEmpty()) {
            $totalInvest = 0;
        } else {
            $totalInvest = $currentData->first()->total_invest;
                $previousDayAgo = $totalInvest;
                $previousWeekAgo = $totalInvest;
                $previousMonthAgo = $totalInvest;
                $previousAllTime = $totalInvest;
            foreach ($currentData as $pnl) {
                $createdAt = Carbon::parse($pnl->created_at);

                if ($createdAt->isYesterday() && $previousDayAgo == $totalInvest) {
                    $previousDayAgo = $pnl->current_price;
                }

                if ($createdAt->isBetween(Carbon::now()->subDays(8), Carbon::now()->subDays(1)) && $previousWeekAgo == $totalInvest) {
                    $previousWeekAgo = $pnl->current_price;
                }

                if ($createdAt->isLastMonth() && $previousMonthAgo == $totalInvest) {
                    $previousMonthAgo = $pnl->current_price;
                }
            }
        }

        $portfolioPnl = new PortfolioPnl();
        $portfolioPnl->portfolio_id = $portfolio->id;
        $portfolioPnl->current_price = $currentPrice;
        $portfolioPnl->day = self::calculatePnl($currentPrice, $previousDayAgo);
        $portfolioPnl->week = self::calculatePnl($currentPrice, $previousWeekAgo);
        $portfolioPnl->month = self::calculatePnl($currentPrice, $previousMonthAgo);
        $portfolioPnl->all_time = self::calculatePnl($currentPrice, $previousAllTime);
        $portfolioPnl->total_invest = $totalInvest;
        $portfolioPnl->save();
    }

}
