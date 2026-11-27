<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $table = 'members';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'first_name',
        'last_name',
        'is_staff',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_staff' => 'integer',
        ];
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
