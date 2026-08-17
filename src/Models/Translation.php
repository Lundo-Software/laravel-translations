<?php

declare(strict_types=1);

namespace Lundo\Translations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Translation extends Model
{
    protected $fillable = ['locale', 'key', 'value'];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
