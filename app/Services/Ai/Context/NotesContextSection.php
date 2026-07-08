<?php

namespace App\Services\Ai\Context;

use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Summarizes the 5 most recent notes for a client and/or project.
 * Note::content is a Filament Builder field (a list of heterogeneous
 * blocks: text, richtext, markdown, code, link), so its text is extracted
 * by walking the array and collecting string values instead of assuming a
 * single format.
 */
class NotesContextSection
{
    private const EXCERPT_LENGTH = 240;

    public function build(?Client $client = null, ?Project $project = null): ?string
    {
        if (! $client && ! $project) {
            return null;
        }

        $notes = Note::query()
            ->where(function (Builder $query) use ($client, $project): void {
                if ($client) {
                    $query->orWhere('client_id', $client->id);
                }

                if ($project) {
                    $query->orWhere('project_id', $project->id);
                }
            })
            ->latest()
            ->limit(5)
            ->get();

        if ($notes->isEmpty()) {
            return null;
        }

        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();

        $lines = $notes->map(function (Note $note) use ($dateFormat): string {
            $excerpt = $this->excerpt($note->content);
            $date = $note->created_at?->format($dateFormat) ?? '-';

            return $excerpt !== ''
                ? "- {$note->title} ({$date}): {$excerpt}"
                : "- {$note->title} ({$date})";
        });

        return "Últimas notas:\n".$lines->implode("\n");
    }

    private function excerpt(mixed $content): string
    {
        $text = trim($this->flatten($content));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return mb_strlen($text) > self::EXCERPT_LENGTH
            ? mb_substr($text, 0, self::EXCERPT_LENGTH).'…'
            : $text;
    }

    private function flatten(mixed $content): string
    {
        if (is_string($content)) {
            return strip_tags(html_entity_decode($content)).' ';
        }

        if (is_array($content)) {
            $text = '';
            foreach ($content as $value) {
                $text .= $this->flatten($value);
            }

            return $text;
        }

        return '';
    }
}
