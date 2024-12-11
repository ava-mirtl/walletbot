<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;
    protected $table = 'portfolios';
    protected $guarded = false;
    public function author()
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id');
    }
    public function tokens()
    {
        return $this->hasMany(Token::class);
    }
    public function pnls()
    {
        return $this->hasMany(PortfolioPnl::class);
    }
    public function last_pnl()
    {
        return $this->hasOne(PortfolioPnl::class)->latest();
    }
}
