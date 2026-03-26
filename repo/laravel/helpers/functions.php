<?php

declare(strict_types=1);

use App\Enums\Pagination;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Helpers\CStr;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Laravel\Scout\Builder as ScoutBuilder;

if (! function_exists('carbon')) {
    /**
     * Returns a new Carbon instance.
     * Please see the testing aids section (specifically static::setTestNow())
     * for more on the possibility of this constructor returning a test instance.
     *
     * @param  DateTimeInterface|string|null  $time
     * @param  DateTimeZone|string|null  $tz
     *
     * @throws  InvalidFormatException
     */
    function carbon(DateTimeInterface|string|null $time = null, DateTimeZone|string|null $tz = null): Carbon
    {
        return new Carbon($time, $tz);
    }
}

if (! function_exists('random_hex')) {
    /**
     * Creates a new hexadecimal string.
     *
     * @param  int  $length
     * @return string
     */
    function random_hex(int $length = 8): string
    {
        $length = clamp(1, $length, 15);

        return Str::upper(str_pad(dechex(rand(0x00_00_00_00, 16 ** $length - 1)), $length, '0', STR_PAD_LEFT));
    }
}

if (! function_exists('slugify')) {
    /**
     * An extension of `Str::slug` with extended dictionary and `Str::words`
     * for limit the string.
     *
     * @param  Stringable|string  $title
     * @param  string  $separator
     * @param  string  $language
     * @param  array<string, string>  $dictionary
     * @param  int  $words
     * @return string
     */
    function slugify(string|Stringable $title, string $separator = '-', string $language = 'en', array $dictionary = [], int $words = 5)
    {
        $dictionary = array_merge(['@' => 'at', '&' => 'and'], $dictionary);

        $title = $words > 0 ? Str::words($title, $words, '') : $title;

        return Str::slug($title, $separator, $language, $dictionary);
    }
}

if (! function_exists('extract_array')) {
    /**
     * Extracts specified keys from provided array if they exist.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $keys
     * @return array<string, mixed>
     */
    function extract_array(array $data, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            // Only string keys are allowed
            if (! $key || ! is_string($key)) {
                continue;
            }

            // Key names ending with exclamation (key1!) will not be checked
            // for nullish values
            $nulls = substr($key, -1) == '!';

            // Remove the exclamation mark (if added)
            $keyName = $nulls ? substr($key, 0, strlen($key) - 1) : $key;

            // The specified key should be included in the data and should not
            // be null (if not omitted)
            if (isset($data[$keyName]) && ($nulls || ! is_null($key))) {
                $result[$keyName] = $data[$keyName];
            }
        }

        return $result;
    }
}

if (! function_exists('array_remove')) {
    /**
     * Removes the specified elements from the numeric arrays.
     *
     * @param  mixed  $keys
     * @param  array  $array
     */
    function array_remove(mixed $keys, array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            // If provided (single) key matches skip this iteration
            if (! is_array($keys) && $keys == $value) {
                continue;
            }

            // If provided keys is an array of keys loop through each key
            if (is_array($keys)) {
                // If a key matches the value skip the outer loop iteration
                foreach ($keys as $key) {
                    if ($key == $value) {
                        continue 2;
                    }
                }
            }

            $result[] = $value;
        }

        return $result;
    }
}

if (! function_exists('prefix_table')) {
    /**
     * Adds table prefix to specified column name(s).
     *
     * @param  Model|string|class-string  $table
     * @param  string|array  $columns
     * @param  bool  $wrap
     * @return array
     */
    function prefix_table(Model|string $table, array|string $columns, bool $wrap = false): array
    {
        $table = match (true) {
            $table instanceof Model => $table->getTable(),
            class_exists($table) => resolve($table)->getTable(),
            default => $table,
        };

        $columns = Arr::wrap($columns);

        return array_map(function (mixed $column) use ($table, $wrap) {
            if (! is_string($column)) {
                return $column;
            }

            return $wrap ? "`{$table}`.`{$column}`" : "{$table}.{$column}";
        }, $columns);
    }
}

if (! function_exists('get_columns')) {
    /**
     * Get columns for specified model/table.
     *
     * @param  Model|string|class-string  $model
     * @param  array<string>  $only
     * @param  array<string>  $except
     * @param  bool  $prefix
     * @return array<string>
     */
    function get_columns(
        Model|string $model,
        array $only = [],
        array $except = [],
        bool $prefix = false,
    ): array {
        $table = match (true) {
            $model instanceof Model => $model->getTable(),
            class_exists($model) => resolve($model)->getTable(),
            default => $model,
        };

        $columns = collect(
            Cache::get(
                sprintf('%s.columns', $table),
                fn () => Schema::getColumnListing($table),
                now()->addSecond(),
            ),
        );

        $only = collect($only)->map(fn (string $column) => Str::squish($column))->filter();
        $except = collect($except)->map(fn (string $column) => Str::squish($column))->filter();

        return $columns
            ->when($only->isNotEmpty(), function (Collection $columns) use (&$only) {
                return $columns->filter(fn (string $column) => $only->contains($column));
            })
            ->when($except->isNotEmpty(), function (Collection $columns) use (&$except) {
                return $columns->filter(fn (string $column) => ! $except->contains($column));
            })
            ->when($prefix, function (Collection $columns) use ($table) {
                return $columns->map(fn (string $column) => "{$table}.{$column}");
            })
            ->all();
    }
}

if (! function_exists('str_limit')) {
    /**
     * Limits the provided string to specific number of characters.
     *
     * @param  string  $string
     * @param  int  $limit
     * @param  string  $end
     * @return string
     */
    function str_limit(string $string, int $limit, $end = ''): string
    {
        return Str::limit($string, $limit, $end);
    }
}

if (! function_exists('get_classname')) {
    /**
     * Extracts the actual class name from provided FQN class name (if class exists).
     *
     * @param  class-string|object  $class
     * @return string
     */
    function get_classname(object|string $class): string
    {
        $classname = is_string($class) ? $class : get_class($class);

        return class_exists($classname) ? last(explode('\\', $classname)) : $classname;
    }
}

if (! function_exists('clamp')) {
    /**
     * Clamps the value between specified limits.
     *
     * @param  float|int  $min
     * @param  float|int  $default
     * @param  float|int  $max
     * @return float|int
     */
    function clamp(float|int $min, float|int $default, float|int $max): float|int
    {
        $min = min($min, $max);
        $max = max($min, $max);

        return $default < $min ? $min : ($default > $max ? $max : $default);
    }
}

if (! function_exists('paginate')) {
    /**
     * Creates paginator for the given query based on request params.
     *
     * @param  EloquentBuilder|Relation|QueryBuilder|ScoutBuilder  $query
     * @param  string|array  $columns
     * @param  string  $name
     * @param  Pagination  $type
     * @param  int  $perPage
     * @param  int  $minPerPage
     * @param  int  $maxPerPage
     * @return ($type is Pagination::Simple ? Paginator : (($type is Pagination::Cursor ? CursorPaginator : LengthAwarePaginator))
     */
    function paginate(
        EloquentBuilder|QueryBuilder|Relation|ScoutBuilder $query,
        array|string $columns = '*',
        string $name = 'page',
        Pagination $type = Pagination::LengthAware,
        int $perPage = 10,
        int $minPerPage = 5,
        int $maxPerPage = 100,
    ): CursorPaginator|LengthAwarePaginator|Paginator {
        $perPage = request()->integer('perPage', $perPage);
        $perPage = clamp($minPerPage, $perPage, $maxPerPage);

        // if ($query instanceof ScoutBuilder) {
        //     return match ($type) {
        //         Pagination::Basic => $query->paginate($perPage, $name),
        //         Pagination::Simple => $query->simplePaginate($perPage, $name),
        //         Pagination::Cursor => $query->cursorPaginate($perPage, $name),
        //         default => throw new InvalidArgumentException("Invalid pagination type [{$type->value}] provided!"),
        //     };
        // }

        return match ($type) {
            Pagination::LengthAware => $query->paginate($perPage, Arr::wrap($columns), $name),
            Pagination::Simple => $query->simplePaginate($perPage, Arr::wrap($columns), $name),
            Pagination::Cursor => $query->cursorPaginate($perPage, Arr::wrap($columns), $name),
            default => throw new InvalidArgumentException("Invalid pagination type [{$type->value}] provided!"),
        };
    }
}

if (! function_exists('image')) {
    /**
     * Returns the URL of specified image.
     *
     * @param  Stringable|string|null  $path
     * @param  string|null  $disk
     * @param  int  $duration
     * @param  array  $options
     * @return string
     */
    function image(string|Stringable|null $path = null, ?string $disk = null, int $duration = 0, array $options = []): string
    {
        $path = $path instanceof Stringable ? $path->toString() : $path;

        if (is_null($path) || strlen($path) == 0) {
            return '';
        }

        if (preg_match('/(http|https):\/\//i', $path)) {
            return $path;
        }

        /** @var Illuminate\Filesystem\FilesystemAdapter|Illuminate\Filesystem\AwsS3V3Adapter $storage */
        $storage = Storage::disk($disk ?: config('filesystems.default'));

        if ($duration > 0) {
            return $storage->temporaryUrl($path, now()->addMinutes(clamp(1, $duration, 60)), $options);
        }

        return $storage->url($path);
    }
}

if (! function_exists('random_filename')) {
    /**
     * Generates a random unique filename.
     *
     * @param  string|null  $extension
     * @return string
     */
    function random_filename(?string $extension = null): string
    {
        $filename = base64_encode(microtime() . random_int(1, 9_999));
        $filename = str_replace(['+', '/', '='], '', $filename);

        return $filename . ($extension ? ".{$extension}" : '');
    }
}

if (! function_exists('random_avatar')) {
    /**
     * Generates a random avatar URL using dicebear.com API.
     *
     * @return string
     */
    function random_avatar(): string
    {
        $styles = [
            'adventurer',
            'adventurer-neutral',
            'avataaars',
            'avataaars-neutral',
            'big-ears',
            'big-ears-neutral',
            'big-smile',
            'bottts',
            'bottts-neutral',
            'croodles',
            'croodles-neutral',
            'fun-emoji',
            // 'icons',
            'identicon',
            'initials',
            'lorelei',
            'lorelei-neutral',
            'micah',
            'notionists',
            'notionists-neutral',
            'open-peeps',
            'personas',
            'pixel-art',
            'pixel-art-neutral',
            'shapes',
            'thumbs',
        ];

        return sprintf('https://api.dicebear.com/6.x/%s/png', $styles[random_int(0, count($styles) - 1)]);
    }
}

if (! function_exists('cn')) {
    /**
     * Parses the given values into HTML class names and applies Tailwind CSS
     * class name merging functions.
     *
     * @param  array<mixed>  $classes
     */
    function cn(...$classes): string
    {
        $classes = collect($classes)->values()->map(function (mixed $class) {
            return match (true) {
                is_array($class) => CStr::classes($class),
                $class instanceof Collection => CStr::classes($class->all()),
                is_string($class) && $class => $class,
                default => null,
            };
        });

        return twMerge($classes->filter()->all());
    }
}

if (! function_exists('initials')) {
    /**
     * Generate initials from a given string.
     *
     * @param  string|Stringable  $string
     * @param  int  $limit
     * @return string
     */
    function initials(string|Stringable $string, int $limit = 2): string
    {
        $initials = collect(explode(' ', (string) $string))
            ->map([Str::class, 'squish'])
            ->filter()
            ->map(fn (string $string) => ucfirst($string[0]));

        return $initials->take(clamp(1, $limit, 10))->join('');
    }
}

if (! function_exists('user')) {
    /**
     * Get the current authenticated user.
     *
     * @return App\Models\User
     */
    function user()
    {
        return optional(Auth::user());
    }
}

if (! function_exists('get_scout_ids')) {
    /**
     * Get the IDs models found by the Scout query.
     *
     * @param  ScoutBuilder  $query
     * @param  string  $key
     * @return list<int>
     */
    function get_scout_ids(ScoutBuilder $query, string $key = 'id'): array
    {
        return collect($query->raw()['hits'])
            ->map(fn (array $hit) => $hit['document'][$key] ?? null)
            ->filter()
            ->all();
    }
}

if (! function_exists('normalize_decimal')) {
    /**
     * Removes zero decimals from provided floating-point value.
     *
     * @param  float  $number
     * @return float|int
     */
    function normalize_decimal(float $number): float|int
    {
        return ($number == (int) $number) ? (int) $number : $number;
    }
}

if (! function_exists('slug')) {
    /**
     * Generate a unique slug for a given model with optional suffixing.
     *
     * @param  Stringable|string  $text
     * @param  class-string<Model>|Model|null  $model
     * @param  Stringable|string  $column
     */
    function slug(string|Stringable $text, Model|string|null $model = null, string|Stringable $column = 'slug'): string
    {
        $slug = slugify($text);

        if ($model) {
            /** @var EloquentBuilder $query */
            $query = match (true) {
                $model instanceof Model => $model->newQuery(),
                is_string($model) => $model::query(),
            };

            if (
                $model instanceof Model
                && $query->clone()->where('id', $model->getKey())->where($column, $slug)->exists()
            ) {
                return $slug; // Model has the same slug
            }

            $query->when($model instanceof Model, function (EloquentBuilder $query) use ($model) {
                $query->whereNot('id', $model->getKey());
            });

            if (($count = $query->count()) > 0) {
                return $slug . '-' . $count + 1;
            }
        }

        return $slug;
    }
}
