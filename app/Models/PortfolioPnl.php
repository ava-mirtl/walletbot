<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioPnl extends Model
{
    use HasFactory;
    protected $table = 'portfolio_pnls';
    protected $guarded = false;
}
