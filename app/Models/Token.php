<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;
    protected $table = 'tokens';
    protected $guarded = false;
    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function pnls()
    {
        return $this->hasMany(Pnl::class);
    }
}
