<?php

declare(strict_types=1);

namespace App\Concerns\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Response;

trait HasSelfHealingUrl
{
    /**
     * Get the database column which will hold the unique slug key.
     *
     * @return string
     */
    public static function getSlugKeyColumn(): string
    {
        return 'uid';
    }

    /**
     * Get the attribute name which will be used to generate modal slug.
     *
     * @return string
     */
    public function getSlugTitleColumn(): string
    {
        return 'name';
    }

    /**
     * The slug title and key separator.
     *
     * @return string
     */
    public static function getSlugSeparator(): string
    {
        return '_';
    }

    /**
     * The attribute name which will trigger route key binding resolution via
     * model slug.
     *
     * @return string
     */
    public static function getSlugResolutionKey(): string
    {
        return 'slug';
    }

    /**
     * Returns the permalink slug.
     *
     * @return string|null
     */
    public function getSlug(): ?string
    {
        if (($title = $this->getSlugTitle()) && ($key = $this->getSlugKey())) {
            return $title . static::getSlugSeparator() . $key;
        }

        return $this->getAttribute($this->getLegacySlugColumn()) ?: $this->getKey();
    }

    /**
     * Get the slug title from specified model attribute.
     *
     * @return string|null
     */
    public function getSlugTitle(): ?string
    {
        $title = $this->{$this->getSlugTitleColumn()};

        return $title ? slugify($title, words: 10) : null;
    }

    /**
     * Get the slug key from specified model attribute.
     *
     * @return string|null
     */
    public function getSlugKey(): ?string
    {
        return $this->{static::getSlugKeyColumn()};
    }

    /**
     * Get the generated permalink of model.
     */
    protected function permalink(): Attribute
    {
        return new Attribute(get: fn () => $this->getSlug());
    }

    /**
     * Resolves the model instance through provided model slug.
     *
     * @param  string  $slug
     * @param  Relation|Builder|null  $query
     * @return static|null
     */
    public static function getModelThroughSlug(string $slug, Builder|Relation|null $query = null): ?static
    {
        return static::getModelSlugResolvingQuery($slug, $query)->first();
    }

    /**
     * Get the Eloquent query for resolving the model through provided slug.
     *
     * @param  string  $slug
     * @param  Relation|Builder|null  $query
     * @return Relation|Builder
     */
    public static function getModelSlugResolvingQuery(string $slug, Builder|Relation|null $query = null): Builder|Relation
    {
        /** @var Relation|Builder */
        $query = $query ?: static::query();

        return $query->where(function (Builder|Relation $query) use (&$slug) {
            $legacyColumn = static::getLegacySlugColumn();
            $model = app(static::class);
            $table = $model->getTable();
            $key = $model->getKeyName();

            $query
                ->when($legacyColumn, fn (Builder|Relation $query) => $query->where("{$table}.{$legacyColumn}", $slug))
                ->orWhere(prefix_table($table, static::getSlugKeyColumn())[0], last(explode(static::getSlugSeparator(), $slug)))
                // If we compare the original value with ID of the model and if
                // the ID of model is integer based then MySQL with collapse
                // the provided value into integer breaking model resolution
                // logic.
                // For example if provided slug is `6ebb8054` then MySQL
                // will compare the ID column as (WHERE `id` = 6)
                // truncating rest of the string which is invalid
                // !Note: We're assuming the primary key will be numeric
                ->when(is_numeric($slug), fn (Builder|Relation $query) => $query->orWhere("{$table}.{$key}", $slug));
        });
    }

    /**
     * Get the legacy static slug column which will be matched when resolving
     * the model.
     *
     * @return string|null
     */
    protected static function getLegacySlugColumn(): ?string
    {
        return null;
    }

    /**
     * Generates a random key used for model identification in provided slug.
     *
     * @return string
     */
    public static function generateSlugKey(): string
    {
        return hash('crc32b', microtime()) . substr(hash('crc32b', microtime()), 0, 2);
    }

    /**
     * Reroutes the request to appropriate URL.
     *
     * @param  string  $parameterValue
     * @param  string  $actualValue
     * @param  string  $provided
     * @param  string  $original
     * @return never
     */
    protected static function reroute(string $parameterValue, string $actualValue): never
    {
        $route = request()->route();

        $originalParameters = $route->originalParameters();
        $paramName = collect($originalParameters)->search(fn ($value) => $parameterValue === $value);

        $url = route(
            $route->getName(),
            [...$originalParameters, $paramName => $actualValue],
        );

        abort(redirect($url, status: Response::HTTP_PERMANENTLY_REDIRECT));
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @param  Relation|Builder|null  $query
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null, Builder|Relation|null $query = null)
    {
        // Pass the resolution logic parent if we're not resolving the model
        // by provided slug key
        if ($field !== static::getSlugResolutionKey()) {
            return parent::resolveRouteBinding($value, $field);
        }

        $model = static::getModelThroughSlug($value, $query);

        // We can safely return null and Laravel will handle the not found
        // exception for us
        if (! $model || $model->getSlug() === $value) {
            return $model;
        }

        // Redirect to appropriate URL if the provided slug does not matches
        // with the intended one
        $this->reroute($value, $model->getSlug());
    }

    /**
     * Hooks custom event into Eloquent model for persisting unique slug key.
     *
     * @return void
     */
    public static function bootHasSelfHealingUrl(): void
    {
        static::creating(function (Model $model) {
            $key = ($model->{static::getSlugKeyColumn()} = static::generateSlugKey());

            // Generate a new slug key if one already exists
            while (static::query()->where(static::getSlugKeyColumn(), $key)->exists()) {
                $key = ($model->{static::getSlugKeyColumn()} = static::generateSlugKey());
            }
        });
    }
}
