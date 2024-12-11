<?php

namespace App\Console\Commands;

use App\Services\PnlManager;
use Illuminate\Console\Command;
use App\Models\Portfolio;

class UpdatePortfolioPnL extends Command
{
    protected $signature = 'portfolio:update-pnl';
    protected $description = 'Обновить PnL для всех портфолио';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Начинаем обновление PnL для всех портфолио.');
        $portfolios = Portfolio::all();
        $pnlManager = new PnlManager();

        foreach ($portfolios as $portfolio) {
            $pnlManager->updatePortfolioPnl($portfolio->id);
            $this->info("PnL обновлен для портфолио ID: {$portfolio->id}");
            sleep(60);
        }

        $this->info('PnL успешно обновлены для всех портфолио.');
    }
}
