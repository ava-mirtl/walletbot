<?php

namespace App\Services;

use App\Models\Pnl;
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
        $previousYearAgo = $totalInvest;
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

                if ($createdAt->isLastYear() && $previousYearAgo == $totalInvest) {
                    $previousYearAgo = $pnl->current_price;
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
        $pnl->year = self::calculatePnl($current, $previousYearAgo);
        $pnl->all_time = self::calculatePnl($current, $previousAllTime);
        $pnl->current_price = $current;
        $pnl->save();
    }
}
