<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $table = 'website_subscribers';

    protected $fillable = ['email', 'locale', 'ip', 'unsubscribed_at'];

    protected $hidden = ['unsubscribe_token'];

    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }
}
