<?php

namespace App\Models;

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

    public function notes()
    {
        return $this->morphedByMany(Note::class, 'taggable');
    }

    public function taggable()
    {
        return $this->hasMany(Taggable::class)->with('taggable');
    }
}
