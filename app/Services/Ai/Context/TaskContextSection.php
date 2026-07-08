<?php

namespace App\Services\Ai\Context;

use App\Helpers\PejotaHelper;
use App\Models\Task;
use Parallax\FilamentComments\Models\FilamentComment;

/**
 * Describes a single task in detail: its own data, parent/children,
 * work sessions logged against it, and comments (parallax/filament-comments).
 * This is distinct from TasksContextSection, which summarizes a client's or
 * project's whole task list.
 */
class TaskContextSection
{
    public function build(Task $task): string
    {
        $task->loadMissing(['status', 'parent', 'children.status', 'workSessions', 'filamentComments.user']);

        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();

        $lines = [
            "Título: {$task->title}",
        ];

        if ($task->status) {
            $lines[] = "Status: {$task->status->name} (fase: {$task->status->phase})";
        }

        if ($task->priority) {
            $lines[] = "Prioridade: {$task->priority}";
        }

        if ($task->due_date) {
            $lines[] = "Vencimento: {$task->due_date->format($dateFormat)}";
        }

        if ($task->planned_start || $task->planned_end) {
            $lines[] = 'Planejado: '.($task->planned_start?->format($dateFormat) ?? '-').' a '.($task->planned_end?->format($dateFormat) ?? '-');
        }

        if ($task->actual_start || $task->actual_end) {
            $lines[] = 'Realizado: '.($task->actual_start?->format($dateFormat) ?? '-').' a '.($task->actual_end?->format($dateFormat) ?? '-');
        }

        if (filled($task->description)) {
            $lines[] = "Descrição:\n".strip_tags(html_entity_decode((string) $task->description));
        }

        if ($task->parent) {
            $lines[] = "Tarefa pai: {$task->parent->title}";
        }

        if ($task->children->isNotEmpty()) {
            $lines[] = 'Subtarefas ('.$task->children->count().'):';
            foreach ($task->children as $child) {
                $lines[] = "- {$child->title} ({$child->status?->name})";
            }
        }

        if ($task->workSessions->isNotEmpty()) {
            $totalMinutes = $task->workSessions->sum('duration');
            $lines[] = 'Sessões de trabalho: '.$task->workSessions->count().' sessão(ões), total '.PejotaHelper::formatDuration($totalMinutes);
        }

        if ($task->filamentComments->isNotEmpty()) {
            $lines[] = 'Comentários internos:';
            foreach ($task->filamentComments as $comment) {
                $lines[] = '- '.$this->commentLine($comment, $dateFormat);
            }
        }

        return "Tarefa:\n".implode("\n", $lines);
    }

    private function commentLine(FilamentComment $comment, string $dateFormat): string
    {
        $author = $comment->user?->name ?? 'Usuário';
        $date = $comment->created_at?->format($dateFormat) ?? '-';

        return "{$author} ({$date}): {$comment->comment}";
    }
}
