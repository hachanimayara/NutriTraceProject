<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const PUBLIC_ROLES = ['producteur', 'transformateur', 'distributeur'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone', 'role', 'status', 'approved_at', 'approved_by', 'google_id', 'is_protected_admin', 'session_version', 'terms_accepted_at', 'terms_version',
        'profile_photo_path', 'organization', 'preferred_locale', 'about',
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
            'approved_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'is_protected_admin' => 'boolean',
            'role_document_uploaded_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function harvestPlans(): HasMany
    {
        return $this->hasMany(HarvestPlan::class);
    }

    public function productRatings(): HasMany
    {
        return $this->hasMany(ProductRating::class, 'consumer_id');
    }

    public function chatConversations(): BelongsToMany
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_conversation_user', 'user_id', 'conversation_id')
            ->withPivot('last_read_at')->withTimestamps();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getDashboardUrl(): string
    {
        return match ($this->role) {
            'admin' => route('admin.dashboard'),
            'producteur' => route('producteur.dashboard'),
            'transformateur' => route('transformateur.dashboard'),
            'distributeur' => route('distributeur.dashboard'),
            default => route('public.catalogue'),
        };
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
