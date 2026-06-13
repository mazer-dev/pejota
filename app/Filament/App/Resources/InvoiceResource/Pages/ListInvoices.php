<?php

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Filament\App\Resources\InvoiceResource;
use App\Filament\App\Widgets\InvoicesOverview;
use App\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InvoicesOverview::class,
        ];
    }

    public function render(): View
    {
        $result = parent::render();

        session()->put('invoices_active_tab_'.auth()->id(), $this->activeTab);

        return $result;
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make()
                ->label(__('Pending'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pending())
                ->badge(fn (): int => Invoice::pending()->count())
                ->badgeColor(Color::Orange),
            'overdue' => Tab::make()
                ->label(__('Overdue'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->overdue())
                ->badge(fn (): int => Invoice::overdue()->count())
                ->badgeColor(Color::Red),
            'delinquent' => Tab::make()
                ->label(__('Delinquent'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->delinquent()),
            'all' => Tab::make()
                ->label(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return session('invoices_active_tab_'.auth()->id(), 'pending');
    }
}
