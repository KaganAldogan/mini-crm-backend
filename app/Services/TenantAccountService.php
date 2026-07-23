<?php

namespace App\Services;

use App\Mail\TenantPortalCredentialsMail;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class TenantAccountService
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
            if ($existing->isTenant()) {
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

            throw new RuntimeException(
                'Bu e-posta adresi personel hesabı olarak kayıtlı. Kiracı hesabı oluşturulamadı.'
            );
        }

        $plainPassword = Str::password(10);

        $user = User::query()->create([
            'name' => $application->name,
            'email' => $application->email,
            'password' => Hash::make($plainPassword),
            'role' => User::ROLE_TENANT,
            'customer_id' => $customer->id,
        ]);

        return [
            'user' => $user,
            'plain_password' => $plainPassword,
            'created' => true,
        ];
    }

    /**
     * Kiracı / hem ev sahibi hem kiracı müşteriler için portal kullanıcısı oluşturur veya bağlar.
     * E-posta yoksa null döner (sözleşme listesine giremez).
     * Yeni hesap oluşturulurken $plainPassword verilmelidir.
     */
    public function ensureForCustomer(Customer $customer, ?string $plainPassword = null): ?User
    {
        if (! in_array($customer->party_type, ['tenant', 'both'], true)) {
            return null;
        }

        $email = strtolower(trim((string) $customer->email));
        if ($email === '') {
            return null;
        }

        $linked = User::query()
            ->where('customer_id', $customer->id)
            ->where('role', User::ROLE_TENANT)
            ->first();

        if ($linked) {
            $update = [
                'name' => $customer->name,
                'email' => $email,
            ];

            if (filled($plainPassword)) {
                $update['password'] = Hash::make($plainPassword);
            }

            $linked->update($update);

            return $linked->fresh();
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->isTenant()) {
                $update = [
                    'name' => $customer->name,
                    'customer_id' => $customer->id,
                ];

                if (filled($plainPassword)) {
                    $update['password'] = Hash::make($plainPassword);
                }

                $existing->update($update);

                return $existing->fresh();
            }

            throw new RuntimeException(
                'Bu e-posta adresi başka bir hesap türüne ait. Kiracı hesabı oluşturulamadı.'
            );
        }

        if (! filled($plainPassword)) {
            throw new RuntimeException(
                'Kiracı portal hesabı için şifre zorunludur.'
            );
        }

        return User::query()->create([
            'name' => $customer->name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'role' => User::ROLE_TENANT,
            'customer_id' => $customer->id,
        ]);
    }

    public function sendCredentialsMail(
        CustomerApplication $application,
        User $user,
        ?string $plainPassword
    ): void {
        Mail::to($user->email)->send(
            new TenantPortalCredentialsMail($application, $user, $plainPassword)
        );
    }
}
