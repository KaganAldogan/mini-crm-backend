<?php

namespace App\Services;

use App\Mail\LandlordPortalCredentialsMail;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LandlordAccountService
{
    /**
     * @return array{user: User, plain_password: string|null, created: bool}
     */
    public function provisionFromApplication(
        CustomerApplication $application,
        Customer $customer
    ): array {
        $existing = User::query()
            ->where('email', $application->email)
            ->first();

        if ($existing) {
            if ($existing->isLandlord()) {
                $existing->update([
                    'name' => $application->name,
                    'customer_id' => $customer->id,
                ]);

                return [
                    'user' => $existing->fresh(),
                    'plain_password' => null,
                    'created' => false,
                ];
            }

            throw new \RuntimeException(
                'Bu e-posta adresi başka bir hesap türü olarak kayıtlı. Ev sahibi hesabı oluşturulamadı.'
            );
        }

        $plainPassword = Str::password(10);

        $user = User::query()->create([
            'name' => $application->name,
            'email' => $application->email,
            'password' => Hash::make($plainPassword),
            'role' => User::ROLE_LANDLORD,
            'customer_id' => $customer->id,
        ]);

        return [
            'user' => $user,
            'plain_password' => $plainPassword,
            'created' => true,
        ];
    }

    public function sendCredentialsMail(
        CustomerApplication $application,
        User $user,
        ?string $plainPassword
    ): void {
        Mail::to($user->email)->send(
            new LandlordPortalCredentialsMail($application, $user, $plainPassword)
        );
    }
}
