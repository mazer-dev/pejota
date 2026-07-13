<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use NunoMazer\Samehouse\BelongsToTenants;

class AssistantMessage extends Model
{
    use BelongsToTenants,
        HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $guarded = ['id'];

    /**
     * Deletes attachments (and their physical files, via the attachment
     * model's own deleting event) whenever a message is deleted through
     * Eloquent, so a conversation/message deletion never leaves orphaned
     * files behind. The FK is also cascadeOnDelete as a DB-level safety net.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $message): void {
            $message->attachments()->get()->each(fn (AssistantMessageAttachment $attachment) => $attachment->delete());
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssistantMessageAttachment::class)->oldest('id');
    }

    /**
     * Markdown-rendered content for display. Raw HTML is stripped and unsafe
     * links are blocked because assistant answers can quote client-originated
     * text coming from the database.
     */
    public function contentHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->content ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]));
    }
}
