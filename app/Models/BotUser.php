<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotUser extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $guarded = [];

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
