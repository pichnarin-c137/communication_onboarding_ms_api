<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalInformation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'personal_information';

    protected $fillable = [
        'user_id',
        'professtional_photo',
        'nationality_card',
        'family_book',
        'birth_certificate',
        'degreee_certificate',
        'social_media',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods for image URLs
    public function getNationalityCardUrlAttribute(): string
    {
        return $this->nationality_card ? asset('storage/'.$this->nationality_card) : '';
    }

    public function getProfesstionalPhotoUrlAttribute(): string
    {
        return $this->professtional_photo ? asset('storage/'.$this->professtional_photo) : '';
    }

    public function getFamilyBookUrlAttribute(): string
    {
        return $this->family_book ? asset('storage/'.$this->family_book) : '';
    }

    public function getBirthCertificateUrlAttribute(): string
    {
        return $this->birth_certificate ? asset('storage/'.$this->birth_certificate) : '';
    }

    public function getDegreeCertificateUrlAttribute(): string
    {
        return $this->degreee_certificate ? asset('storage/'.$this->degreee_certificate) : '';
    }
}
