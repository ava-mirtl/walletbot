<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;
    protected $table = 'telegram_users';
    protected $guarded = false;
    public function portfolio(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Portfolio::class, 'telegram_user_id');
    }
}
