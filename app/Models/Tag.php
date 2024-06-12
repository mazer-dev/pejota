<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Tags\Tag as SpatieTag;

class Tag extends SpatieTag
{
    public function tasks()
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    public function projects()
    {
        return $this->morphedByMany(Project::class, 'taggable');
    }

    public function taggable()
    {
        return $this->hasMany(Taggable::class)->with('taggable');
    }
}
