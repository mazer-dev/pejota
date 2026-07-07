<?php

namespace App\Filament\App\Resources\InvoiceResource\RelationManagers;

use App\Helpers\PejotaHelper;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Deliveries';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')->translateLabel()->badge(),
                TextColumn::make('channel')->translateLabel()->badge(),
                TextColumn::make('to')->translateLabel()->badge()->separator(','),
                TextColumn::make('subject')->translateLabel()->wrap(),
                TextColumn::make('attachments_meta')->label(__('Attachments'))->badge()->separator(','),
                TextColumn::make('sent_at')->translateLabel()->dateTime(PejotaHelper::getUserDateTimeFormat())->placeholder('—'),
                TextColumn::make('error')->translateLabel()->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([10, 25]);
    }
}
