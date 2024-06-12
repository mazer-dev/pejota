<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Taggable extends Pivot
{
    protected $table = 'taggables';

    public function taggable()
    {
        return $this->morphTo();
    }
}
