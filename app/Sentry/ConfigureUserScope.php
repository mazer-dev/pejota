<?php

namespace App\Sentry;

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Sentry\State\Scope;

use function Sentry\configureScope;

class ConfigureUserScope
{
    public function __invoke(Authenticated $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $data = $this->data($user);

        if ($data === []) {
            return;
        }

        configureScope(function (Scope $scope) use ($data): void {
            $scope->setUser($data['user']);

            if (isset($data['tags']['company'])) {
                $scope->setTag('company', $data['tags']['company']);
                $scope->setContext('company', $data['context']);
            }
        });
    }

    /**
     * @return array{user?: array{id: int, email: string|null}, tags?: array{company: string}, context?: array{id: int, name: string|null}}
     */
    public function data(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $data = [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ];

        $company = $user->company;

        if ($company !== null) {
            $data['tags'] = ['company' => (string) $company->id];
            $data['context'] = ['id' => $company->id, 'name' => $company->name];
        }

        return $data;
    }
}
