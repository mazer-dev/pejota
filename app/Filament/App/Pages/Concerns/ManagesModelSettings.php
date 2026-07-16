<?php

namespace App\Filament\App\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Glorand\Model\Settings\Managers\AbstractSettingsManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Native replacement for the removed quadrubo/filament-model-settings base page.
 *
 * Hosts a Filament v5 schema (state path "data") and bridges it to the Glorand
 * model-settings storage layer on the record returned by getSettingRecord().
 * Consuming pages must extend Filament\Pages\Page, implement HasForms, declare a
 * $view rendering {{ $this->form }} plus getCachedFormActions(), and define their
 * own form(Schema $schema) applying ->statePath('data').
 */
trait ManagesModelSettings
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    public ?array $data = [];

    abstract public static function getSettingRecord(): Model;

    public function mount(): void
    {
        $this->form->fill(static::getSettingRecord()->settings()->all());
    }

    /**
     * Persist the form state back to Glorand non-destructively.
     *
     * setMultiple() merges each dot-path leaf into the record's existing
     * settings (read from all()), so keys that have no form field - e.g.
     * docs.invoice_number_last, a sibling of the form-managed
     * docs.invoice_number_format - survive the save. Glorand's dotFlatten()
     * only descends into associative arrays, keeping list values (CheckboxList)
     * whole so a shrinking selection replaces its leaf instead of leaving stale
     * indexes behind.
     */
    public function save(): void
    {
        static::getSettingRecord()->settings()->setMultiple(
            AbstractSettingsManager::dotFlatten($this->form->getState())
        );

        Notification::make()
            ->success()
            ->title(__('Saved'))
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }
}
