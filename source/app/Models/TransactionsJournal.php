<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionsJournal extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'deleted_at', 'date', 'budget_date', 'description', 'transaction_number'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function transactions() {
        return $this->hasMany(Transaction::class)
            ->where('deleted_at',null);
    }
}
