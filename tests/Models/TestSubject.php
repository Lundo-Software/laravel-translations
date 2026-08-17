<?php

declare(strict_types=1);

namespace Lundo\Translations\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Lundo\Translations\Traits\HasTranslations;

final class TestSubject extends Model
{
    use HasTranslations;

    protected $table = 'test_subjects';

    protected $fillable = ['name', 'sub_title', 'intro'];

    protected array $translatable = ['name', 'sub_title', 'intro'];

    protected $with = ['translations'];
}
