<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company',
        'role',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the surveys created by this user.
     */
    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'created_by');
    }

    /**
     * Get the responses submitted by this user.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'user_id');
    }

    /**
     * Get user's survey statistics.
     */
    public function getSurveyStatsAttribute(): array
    {
        return [
            'total_surveys' => $this->surveys()->count(),
            'published_surveys' => $this->surveys()->where('status', 'published')->count(),
            'draft_surveys' => $this->surveys()->where('status', 'draft')->count(),
            'total_responses' => $this->surveys()->withCount('responses')->get()->sum('responses_count'),
        ];
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is manager.
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }
}
