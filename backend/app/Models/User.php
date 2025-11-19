<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rank',
        'rank_level',
        // Discord fields
        'discord_id',
        'discord_name',
        'discord_avatar',
        // RSI fields
        'rsi_handle',
        'rsi_verified_at',

        // Verification fields
        'verification_code',
        'verification_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rank_level' => 'integer',
            'rsi_verified_at' => 'datetime',
        ];
    }
    public function setRank(string $rank): void
    {
        $map = [
            'member'         => 1,
            'lieutenant'     => 2,
            'commander'      => 3,
            'wing_commander' => 4,
            'admiral'        => 5,
            'grand_admiral'  => 6,
        ];

        $this->attributes['rank'] = $rank;
        $this->attributes['rank_level'] = $map[$rank] ?? 1;
        $this->save();
    }

    public function getRankNameAttribute()
    {
        return [
            1 => 'Member',
            2 => 'Lieutenant',
            3 => 'Commander',
            4 => 'Wing Commander',
            5 => 'Admiral',
            6 => 'Grand Admiral',
        ][$this->rank_level] ?? 'Unknown';
    }
}