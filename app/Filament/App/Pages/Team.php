<?php

namespace App\Filament\App\Pages;

use App\Enums\CompanyRoleEnum;
use App\Enums\MenuGroupsEnum;
use App\Exceptions\InvitationException;
use App\Helpers\PejotaHelper;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\Mail\InvitationMailer;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Team extends Page implements HasTable
{
    use InteractsWithFormActions, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static string $view = 'filament.app.pages.team';

    public Company $company;

    public function mount(): void
    {
        $this->company = PejotaHelper::currentCompany();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole([
            CompanyRoleEnum::Owner->value,
            CompanyRoleEnum::Admin->value,
        ]) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return __('Team');
    }

    public static function getNavigationLabel(): string
    {
        return __('Team');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('invite')
                ->label(__('Invite member'))
                ->icon('heroicon-o-envelope')
                ->form([
                    TextInput::make('email')->label(__('Email'))->email()->required()->maxLength(255),
                    Select::make('role')
                        ->label(__('Role'))
                        ->options([
                            CompanyRoleEnum::Admin->value => __('Admin'),
                            CompanyRoleEnum::Member->value => __('Member'),
                        ])
                        ->default(CompanyRoleEnum::Member->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InvitationService::class)->invite(
                            $this->company,
                            $data['email'],
                            CompanyRoleEnum::from($data['role']),
                            auth()->user(),
                        );
                        Notification::make()->success()->title(__('Invitation sent'))->send();
                    } catch (InvitationException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }

    /** @return Collection<int, Invitation> */
    public function pendingInvitations(): Collection
    {
        return $this->company->invitations()
            ->whereNull('accepted_at')
            ->latest()
            ->get();
    }

    public function resendInvitation(int $invitationId): void
    {
        $invitation = $this->company->invitations()
            ->whereKey($invitationId)
            ->whereNull('accepted_at')
            ->first();

        if ($invitation === null) {
            return;
        }

        app(InvitationMailer::class)->send($invitation);
        Notification::make()->success()->title(__('Invitation resent'))->send();
    }

    public function revokeInvitation(int $invitationId): void
    {
        $deleted = $this->company->invitations()
            ->whereKey($invitationId)
            ->whereNull('accepted_at')
            ->delete();

        if ($deleted > 0) {
            Notification::make()->success()->title(__('Invitation revoked'))->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->whereKey(
                $this->company->users()->wherePivotNotNull('joined_at')->pluck('users.id')->all()
            ))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('email')->label(__('Email'))->searchable(),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->state(fn (User $record): ?string => $record->getRoleNames()->first()),
            ])
            ->actions([
                Action::make('changeRole')
                    ->label(__('Change role'))
                    ->icon('heroicon-o-arrows-up-down')
                    ->form([
                        Select::make('role')
                            ->label(__('Role'))
                            ->options(function (): array {
                                $cases = auth()->user()->hasRole(CompanyRoleEnum::Owner->value)
                                    ? CompanyRoleEnum::cases()
                                    : [CompanyRoleEnum::Admin, CompanyRoleEnum::Member];

                                return collect($cases)
                                    ->mapWithKeys(fn (CompanyRoleEnum $r): array => [$r->value => __(ucfirst($r->value))])
                                    ->all();
                            })
                            ->required(),
                    ])
                    ->fillForm(fn (User $record): array => ['role' => $record->getRoleNames()->first()])
                    ->action(function (User $record, array $data): void {
                        try {
                            app(InvitationService::class)->changeRole(
                                $this->company,
                                $record,
                                CompanyRoleEnum::from($data['role']),
                                auth()->user(),
                            );
                            Notification::make()->success()->title(__('Role updated'))->send();
                        } catch (InvitationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
                Action::make('remove')
                    ->label(__('Remove'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        try {
                            app(InvitationService::class)->removeMember($this->company, $record, auth()->user());
                            Notification::make()->success()->title(__('Member removed'))->send();
                        } catch (InvitationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }
}
