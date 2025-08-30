<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'category',
        'estimated_time',
        'questions',
        'settings',
        'status',
        'created_by',
        'published_at',
        'analytics',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'questions' => 'array',
        'settings' => 'array',
        'analytics' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($survey) {
            if (empty($survey->analytics)) {
                $survey->analytics = [
                    'total_responses' => 0,
                    'completion_rate' => 0,
                    'average_time' => 0,
                    'nps_score' => null,
                    'sentiment_breakdown' => [
                        'positive' => 0,
                        'neutral' => 0,
                        'negative' => 0
                    ]
                ];
            }
        });
    }

    /**
     * Get the user who created this survey.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the responses for this survey.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * Get the questions for this survey.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Scope a query to only include published surveys.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope a query to only include draft surveys.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include surveys by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope a query to only include surveys by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if the survey is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if the survey is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the survey is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Publish the survey.
     */
    public function publish(): bool
    {
        $this->status = 'published';
        $this->published_at = now();
        return $this->save();
    }

    /**
     * Close the survey.
     */
    public function close(): bool
    {
        $this->status = 'closed';
        return $this->save();
    }

    /**
     * Pause the survey.
     */
    public function pause(): bool
    {
        $this->status = 'paused';
        return $this->save();
    }

    /**
     * Get survey analytics.
     */
    public function getAnalyticsAttribute($value): array
    {
        $analytics = json_decode($value, true) ?? [];
        
        // Calculate real-time analytics
        $totalResponses = $this->responses()->count();
        $completedResponses = $this->responses()->whereNotNull('completion_time')->count();
        $avgTime = $this->responses()->whereNotNull('completion_time')->avg('completion_time');
        $npsScores = $this->responses()->whereNotNull('nps_score')->pluck('nps_score');
        
        // Calculate NPS
        $npsScore = null;
        if ($npsScores->count() > 0) {
            $promoters = $npsScores->filter(fn($score) => $score >= 9)->count();
            $detractors = $npsScores->filter(fn($score) => $score <= 6)->count();
            $npsScore = round((($promoters - $detractors) / $npsScores->count()) * 100);
        }

        // Sentiment breakdown
        $sentimentBreakdown = [
            'positive' => $this->responses()->where('sentiment', 'positive')->count(),
            'neutral' => $this->responses()->where('sentiment', 'neutral')->count(),
            'negative' => $this->responses()->where('sentiment', 'negative')->count(),
        ];

        return array_merge($analytics, [
            'total_responses' => $totalResponses,
            'completion_rate' => $totalResponses > 0 ? round(($completedResponses / $totalResponses) * 100, 2) : 0,
            'average_time' => $avgTime ? round($avgTime) : 0,
            'nps_score' => $npsScore,
            'sentiment_breakdown' => $sentimentBreakdown,
            'last_updated' => now()->toISOString(),
        ]);
    }

    /**
     * Get survey URL.
     */
    public function getUrlAttribute(): string
    {
        return config('app.frontend_url', 'https://survey.messageboost.ai') . '/survey/' . $this->id;
    }

    /**
     * Get survey share URL.
     */
    public function getShareUrlAttribute(): string
    {
        return $this->url . '?ref=share';
    }

    /**
     * Get estimated completion time in minutes.
     */
    public function getEstimatedMinutesAttribute(): int
    {
        // Extract minutes from estimated_time string like "5-10 minutes"
        preg_match('/(\d+)/', $this->estimated_time, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 5;
    }

    /**
     * Get question count.
     */
    public function getQuestionCountAttribute(): int
    {
        return is_array($this->questions) ? count($this->questions) : 0;
    }

    /**
     * Get response rate percentage.
     */
    public function getResponseRateAttribute(): float
    {
        // This would be calculated based on survey distribution data
        // For now, return a calculated value based on responses
        $totalResponses = $this->responses()->count();
        $estimatedViews = $totalResponses * 1.5; // Assume 67% completion rate
        
        return $estimatedViews > 0 ? round(($totalResponses / $estimatedViews) * 100, 2) : 0;
    }

    /**
     * Update survey analytics.
     */
    public function updateAnalytics(): void
    {
        // Force refresh of analytics
        $this->refresh();
        $this->save();
    }
}
