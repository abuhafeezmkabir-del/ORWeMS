<?php

namespace App\Enums;

use App\Models\Community;
use App\Models\Country;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Model;

/**
 * The geographic levels an ORWeMS access scope can be granted at.
 *
 * These are the stable machine-readable identifiers stored in
 * `user_geographic_scopes.level`. They are deliberately NOT numeric codes: a
 * level is compared, indexed and audited, so an integer that silently changes
 * meaning would be a security defect, whereas a string that reads
 * `community` cannot be misread.
 *
 * The level of a scope is always one of these five values, and each value maps
 * to exactly one foreign-key column and one Eloquent model. Keeping that map in
 * the enum is what lets the rest of the system resolve a scope generically
 * (`$level->column()`, `$level->modelClass()`) without a switch statement
 * repeated in every caller — and without inventing a polymorphic reference,
 * which would have thrown away the foreign keys that keep the geography
 * hierarchy honest.
 */
enum GeographicScopeLevel: string
{
    case Country = 'country';
    case State = 'state';
    case Lga = 'lga';
    case Ward = 'ward';
    case Community = 'community';

    /**
     * The column on `user_geographic_scopes` that carries this level's target.
     *
     * Exactly one of these columns is non-null on any given scope row; the
     * others must be null. That invariant is what allows one table to hold all
     * five levels while keeping a real foreign key on every one of them.
     */
    public function column(): string
    {
        return match ($this) {
            self::Country => 'country_id',
            self::State => 'state_id',
            self::Lga => 'local_government_area_id',
            self::Ward => 'ward_id',
            self::Community => 'community_id',
        };
    }

    /**
     * The Eloquent model this level points at.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Country => Country::class,
            self::State => State::class,
            self::Lga => LocalGovernmentArea::class,
            self::Ward => Ward::class,
            self::Community => Community::class,
        };
    }

    /**
     * Human-readable label. Presentation only — never an authorization key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Country => 'Country',
            self::State => 'State',
            self::Lga => 'Local Government Area',
            self::Ward => 'Ward',
            self::Community => 'Community',
        };
    }

    /**
     * Every level column on `user_geographic_scopes`.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_map(
            static fn (self $level): string => $level->column(),
            self::cases()
        );
    }

    /**
     * Every level column except this one — the ones that must stay null.
     *
     * @return list<string>
     */
    public function otherColumns(): array
    {
        return array_values(array_filter(
            self::columns(),
            fn (string $column): bool => $column !== $this->column()
        ));
    }

    /**
     * The level that owns a given geography model, or null if the model is not
     * part of the ORWeMS geographic hierarchy at all.
     *
     * Returning null rather than throwing is deliberate: a caller handed an
     * unrelated model (a Beneficiary, say) must be able to treat that as "no
     * scope can cover this" and deny, rather than crashing the request.
     */
    public static function tryForModel(Model $geography): ?self
    {
        foreach (self::cases() as $level) {
            $modelClass = $level->modelClass();

            if ($geography instanceof $modelClass) {
                return $level;
            }
        }

        return null;
    }

    /**
     * The level that owns a geography model, or throws if there is none.
     *
     * Used where the caller has already decided the model must be a geographic
     * one, so a mismatch is a programming error rather than a denial.
     */
    public static function forModel(Model $geography): self
    {
        $level = self::tryForModel($geography);

        if ($level === null) {
            throw new \InvalidArgumentException(sprintf(
                'The model [%s] is not part of the ORWeMS geographic hierarchy.',
                $geography::class
            ));
        }

        return $level;
    }
}
