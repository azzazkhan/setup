<?php

declare(strict_types=1);

namespace App\Concerns\Enum;

use Illuminate\Support\Arr;

trait Resolvable
{
    /**
     * Resolve the enum either from instance or case value.
     *
     * @param  static|string|int  $value
     * @param  bool  $strict
     * @return ($strict is true ? static : static|null)
     */
    public static function resolve(self|string $value, bool $strict = false)
    {
        if ($value instanceof static) {
            return $value;
        }

        return $strict ? static::from($value) : static::tryFrom($value);
    }

    /**
     * @param  mixed  $cases
     * @return bool
     */
    public function is(mixed $cases): bool
    {
        foreach (Arr::wrap($cases) as $case) {
            $case = static::resolve($case);

            $result = match (true) {
                $case instanceof self => $case->value == $this->value,
                default => false,
            };

            if ($result) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $roles
     * @return bool
     */
    public function isNot(mixed $roles): bool
    {
        return ! $this->is($roles);
    }
}
