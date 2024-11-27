<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;
    protected $table = 'contracts';
    protected $guarded = false;
    public function portfolios()
    {
        return $this->belongsToMany(Portfolio::class, 'portfolio_contract', 'contract_id', 'portfolio_id');
    }
}
