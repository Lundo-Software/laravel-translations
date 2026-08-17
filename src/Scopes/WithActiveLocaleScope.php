<?php

declare(strict_types=1);

namespace Lundo\Translations\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Lundo\Translations\Traits\HasTranslations;

/**
 * Opt-in scope: eager loads only the active locale's translations instead of all locales.
 * Add via $model->addGlobalScope(new WithActiveLocaleScope) when performance is critical.
 */
class WithActiveLocaleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! in_array(HasTranslations::class, class_uses_recursive($model), true)) {
            return;
        }

        $builder->with(['translations' => fn ($q) => $q->where('locale', app()->getLocale())]);
    }
}
