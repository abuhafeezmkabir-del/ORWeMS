<?php

namespace App\Models;

use App\Enums\BeneficiaryStatus;
use App\Enums\DateAccuracy;
use App\Enums\Sex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Beneficiary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'beneficiary_ref', 'first_name', 'middle_name', 'last_name',
        'sex', 'date_of_birth', 'date_of_birth_accuracy',
        'nationality_country_id', 'country_id', 'state_id',
        'local_government_area_id', 'ward_id', 'community_id',
        'household_id', 'person_id', 'programme_id',
        'registration_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'date_of_birth' => 'date',
            'date_of_birth_accuracy' => DateAccuracy::class,
            'status' => BeneficiaryStatus::class,
            'registration_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The reference column is NOT NULL + UNIQUE, so a provisional unique
        // value is written on insert and then replaced with the stable
        // reference derived from the primary key. The final reference is
        // unique, stable and free of personal characteristics.
        static::creating(function (Beneficiary $beneficiary) {
            if (empty($beneficiary->beneficiary_ref)) {
                $beneficiary->beneficiary_ref = 'PENDING-'.Str::ulid();
            }
        });

        static::created(function (Beneficiary $beneficiary) {
            if (str_starts_with((string) $beneficiary->beneficiary_ref, 'PENDING-')) {
                $beneficiary->beneficiary_ref = self::buildReference($beneficiary->getKey());
                $beneficiary->saveQuietly();
            }
        });
    }

    /**
     * Build the stable internal reference for a beneficiary primary key,
     * e.g. WICHOV-000001. The prefix and padding are configurable in
     * config/orwems.php so no programme identity is hard-coded.
     */
    public static function buildReference(int|string $key): string
    {
        $prefix = (string) config('orwems.beneficiary_ref_prefix', 'WICHOV');
        $padding = (int) config('orwems.beneficiary_ref_padding', 6);

        return sprintf('%s-%s', $prefix, str_pad((string) $key, $padding, '0', STR_PAD_LEFT));
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function localGovernmentArea(): BelongsTo
    {
        return $this->belongsTo(LocalGovernmentArea::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('assessment_date');
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }
}
