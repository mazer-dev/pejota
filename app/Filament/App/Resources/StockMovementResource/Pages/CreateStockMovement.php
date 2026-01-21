<?php

namespace App\Filament\App\Resources\StockMovementResource\Pages;

use App\Filament\App\Resources\StockMovementResource;
use App\Models\Company;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    public function mount(): void
    {
        $companyId = $this->getUserCompanyId();
        
        if (!$companyId) {
            Notification::make()
                ->title('Company Required')
                ->body('Please set up your company first.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect('/app/my-company');
            return;
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getUserCompanyId();
        return $data;
    }

    private function getUserCompanyId(): ?int
    {
        $userId = Auth::id();
        $user = User::find($userId);
        
        if ($user->company_id) {
            return $user->company_id;
        }

        $company = Company::where('user_id', $userId)->first();
        
        if ($company) {
            $user->company_id = $company->id;
            $user->save();
            return $company->id;
        }

        return null;
    }
}