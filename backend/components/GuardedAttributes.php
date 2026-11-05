<?php

namespace backend\components;

/**
 * Keeps server-controlled columns out of mass assignment ($model->load()).
 *
 * The attributes listed in guardedAttributes() are still validated but are
 * marked unsafe ("!attr") in every scenario, so a crafted POST cannot set
 * them; controllers assign them explicitly.
 */
trait GuardedAttributes
{
    /** @return string[] attributes that must never come from a form */
    abstract protected static function guardedAttributes(): array;

    public function scenarios(): array
    {
        $guarded   = static::guardedAttributes();
        $scenarios = parent::scenarios();
        foreach ($scenarios as $name => $attrs) {
            $scenarios[$name] = array_map(fn($a) => in_array(ltrim($a, '!'), $guarded, true) ? '!' . ltrim($a, '!') : $a, $attrs);
        }
        return $scenarios;
    }
}
