<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Pnl;
use App\Models\Portfolio;
use App\Models\PortfolioPnl;
use App\Models\Token;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AchievementManager
{

    public function updateWalletAchievements($portfolio)
    {
        $pnl = $portfolio->last_pnl;
        if (!$pnl) return;

        $totalInvest = $pnl->total_invest;
        $currentPrice = $pnl->current_price;

        $this->checkAchievement($portfolio, 'wallet', $totalInvest, $currentPrice);
    }

    public function updateTokenAchievements($token)
    {
        $pnl = $token->last_pnl;
        if (!$pnl) return;

        $totalInvest = $token->total_invest;
        $currentPrice = $pnl->current_price;

        $this->checkAchievement($token, 'token', $totalInvest, $currentPrice);
    }

    private function checkAchievement($object, $type, $totalInvest, $currentPrice)
    {
        $multiplier = $currentPrice / $totalInvest;

        if ($multiplier >= 2) {
            $this->addAchievement($object, $type, 'x2');
        }
        if ($multiplier >= 3) {
            $this->addAchievement($object, $type, 'x3');
        }
        if ($multiplier >= 4) {
            $this->addAchievement($object, $type, 'x4');
        }
        if ($multiplier >= 10) {
            $this->addAchievement($object, $type, 'x10');
        }
    }

    private function addAchievement($object, $type, $achievement)
    {
        Achievement::firstOrCreate(
            [
                'portfolio_id' => $type === 'wallet' ? $object->id : null,
                'token_id' => $type === 'token' ? $object->id : null,
                'type' => $type,
                'achievement' => $achievement,
            ]
        );
    }

}
