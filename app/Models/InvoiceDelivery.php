<?php

namespace App\Models;

use App\Enums\DeliveryChannelEnum;
use App\Enums\DeliveryStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class InvoiceDelivery extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatusEnum::class,
            'channel' => DeliveryChannelEnum::class,
            'to' => 'array',
            'cc' => 'array',
            'timesheet_params' => 'array',
            'attachments_meta' => 'array',
            'attach_invoice_pdf' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }
}
