<?php

namespace App\Services;

use App\Models\Pnl;
use App\Models\Portfolio;
use App\Models\PortfolioPnl;
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
        // Перебираем все токены и обновляем их цены
        foreach ($tokens as $token) {
            $newPrice = $this->fetchTokenPrice($token);
            $newNetwPrice = $this->fetchNetworkPrice($token->network);
            if ($newPrice !== null) {
                $portfolioCurrentPrice += $newPrice* $token->total_amount+$token->profit;
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
    private function fetchTokenPrice($token)
    {
        //toDo：переписать, чтобы массив адресов отправлять и получать их объекты

        $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://api.geckoterminal.com/api/v2/networks/' . $token->network . '/tokens/multi/' . $token->address);
            $apiResponse = json_decode($response->getBody());

            if (isset($apiResponse->data) && count($apiResponse->data) > 0) {
                return $apiResponse->data[0]->attributes->price_usd;
            } else return null;
    }
    public function fetchNetworkPrice($network)
    {
        //toDo：переписать, чтобы возвращать массив всех этих монет за 1 запрос
            $full_network_name = '';
            switch ($network) {
                case 'eth':
                    $full_network_name = 'ethereum';
                    break;
                case 'sol':
                    $full_network_name = 'solana';
                    break;
                case 'bsc':
                    $full_network_name = 'binancecoin';
                    break;
                case 'ton':
                    $full_network_name = 'toncoin';
                    break;
                case 'trx':
                    $full_network_name = 'tron';
                    break;
                case 'base':
                    $full_network_name = 'base';
                    break;
                default:
                    throw new Exception('Unsupported network');
            }
            $url ="https://api.coingecko.com/api/v3/simple/price?ids=$full_network_name&vs_currencies=usd";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);
            $apiResponse = json_decode($response->getBody(), true);

            if (isset($apiResponse[$full_network_name]['usd'])) {
                return $apiResponse[$full_network_name]['usd'];
            }

            return null;
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
