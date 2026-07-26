<?php

namespace App\Filament\App\Pages;

use App\Enums\DailyPlanItemTypeEnum;
use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\ContractResource\Pages\EditContract;
use App\Filament\App\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Helpers\PejotaHelper;
use App\Jobs\GenerateDailyPlan;
use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Services\Planner\PlannerCapacity;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class PlanOfTheDay extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.app.pages.plan-of-the-day';

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::DAILY_WORK->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('Plan of the day');
    }

    public function getTitle(): string
    {
        return __('Plan of the day');
    }

    #[Computed]
    public function plan(): ?DailyPlan
    {
        return DailyPlan::query()
            ->where('company_id', auth()->user()->company->id)
            ->forDate($this->today())
            ->with(['items.task', 'items.invoice', 'items.contract', 'items.client', 'items.conversation'])
            ->first();
    }

    #[Computed]
    public function workedTodayMinutes(): int
    {
        return PlannerCapacity::forCompany(auth()->user()->company)->workedOnDayMinutes($this->today());
    }

    public function markItemDone(int $itemId): void
    {
        $this->findItem($itemId)?->markDone();
        unset($this->plan);
    }

    public function skipItem(int $itemId): void
    {
        $this->findItem($itemId)?->markSkipped();
        unset($this->plan);
    }

    public function reopenItem(int $itemId): void
    {
        $this->findItem($itemId)?->reopen();
        unset($this->plan);
    }

    public function generatePlan(): void
    {
        $company = auth()->user()->company;
        $today = $this->today();

        $mode = PlannerCapacity::forCompany($company)->isWorkDay($today)
            ? DailyPlanModeEnum::FULL
            : DailyPlanModeEnum::LIGHT;

        $plan = DailyPlan::query()
            ->where('company_id', $company->id)
            ->forDate($today)
            ->first();

        if ($plan) {
            $plan->update(['mode' => $mode, 'status' => DailyPlanStatusEnum::GENERATING, 'failure_reason' => null]);
        } else {
            DailyPlan::query()->create([
                'company_id' => $company->id,
                'plan_date' => $today->toDateString(),
                'mode' => $mode,
                'status' => DailyPlanStatusEnum::GENERATING,
            ]);
        }

        GenerateDailyPlan::dispatch($company, $today->toDateString(), $mode->value);

        Notification::make()
            ->title(__('Generating your plan of the day...'))
            ->body(__('The AI is analyzing all your data. This can take a few minutes.'))
            ->info()
            ->send();

        unset($this->plan);
    }

    public function generateExtra(int $minutes): void
    {
        if ($minutes <= 0) {
            Notification::make()
                ->title(__('Enter how much extra time you want to work.'))
                ->warning()
                ->send();

            return;
        }

        $company = auth()->user()->company;
        $today = $this->today();

        $plan = DailyPlan::query()
            ->where('company_id', $company->id)
            ->forDate($today)
            ->first();

        if ($plan) {
            $plan->update(['mode' => DailyPlanModeEnum::FULL, 'status' => DailyPlanStatusEnum::GENERATING, 'failure_reason' => null]);
        } else {
            DailyPlan::query()->create([
                'company_id' => $company->id,
                'plan_date' => $today->toDateString(),
                'mode' => DailyPlanModeEnum::FULL,
                'status' => DailyPlanStatusEnum::GENERATING,
            ]);
        }

        GenerateDailyPlan::dispatch($company, $today->toDateString(), DailyPlanModeEnum::FULL->value, $minutes);

        Notification::make()
            ->title(__('Planning :time of extra work...', ['time' => PejotaHelper::formatDuration($minutes)]))
            ->info()
            ->send();

        unset($this->plan);
    }

    public function itemUrl(DailyPlanItem $item): ?string
    {
        return match (true) {
            $item->task_id !== null => ViewTask::getUrl([$item->task_id]),
            $item->invoice_id !== null => ViewInvoice::getUrl([$item->invoice_id]),
            $item->contract_id !== null => EditContract::getUrl([$item->contract_id]),
            $item->whatsapp_conversation_id !== null => ViewWhatsappConversation::getUrl([$item->whatsapp_conversation_id]),
            default => null,
        };
    }

    public function itemTypeEnum(DailyPlanItem $item): DailyPlanItemTypeEnum
    {
        return $item->type;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(fn (): string => $this->plan ? __('Regenerate plan') : __('Generate plan'))
                ->icon('heroicon-o-sparkles')
                ->disabled(fn (): bool => (bool) $this->plan?->isGenerating())
                ->requiresConfirmation(fn (): bool => (bool) $this->plan?->isReady())
                ->modalHeading(__('Regenerate plan'))
                ->modalDescription(__('The current plan and its progress will be replaced. Continue?'))
                ->action('generatePlan'),

            Action::make('extraTime')
                ->label(__('Work extra time'))
                ->icon('heroicon-o-plus-circle')
                ->color('gray')
                ->disabled(fn (): bool => (bool) $this->plan?->isGenerating())
                ->modalHeading(__('Work extra time'))
                ->modalDescription(__('Plan the next best work for the extra time you want to put in now, beyond the day plan.'))
                ->form([
                    TextInput::make('hours')
                        ->label(__('Hours'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(12)
                        ->default(0),
                    TextInput::make('minutes')
                        ->label(__('Minutes'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(59)
                        ->default(30),
                ])
                ->action(function (array $data): void {
                    $minutes = ((int) ($data['hours'] ?? 0)) * 60 + ((int) ($data['minutes'] ?? 0));

                    $this->generateExtra($minutes);
                }),
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfDay();
    }

    private function findItem(int $itemId): ?DailyPlanItem
    {
        return DailyPlanItem::query()
            ->where('company_id', auth()->user()->company->id)
            ->find($itemId);
    }
}
