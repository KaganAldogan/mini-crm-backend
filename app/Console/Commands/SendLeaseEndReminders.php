<?php

namespace App\Console\Commands;

use App\Mail\LeaseEndingSoonMail;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLeaseEndReminders extends Command
{
    protected $signature = 'leases:send-end-reminders';

    protected $description = 'Sözleşme bitiş hatırlatma e-postalarını gönderir (tercih açık olanlara)';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        $leases = Lease::query()
            ->with(['tenant', 'property', 'landlord'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->get();

        foreach ($leases as $lease) {
            $daysLeft = $lease->daysUntilEnd($today);
            if ($daysLeft === null || $daysLeft < 0) {
                continue;
            }

            $recipients = $this->recipientsFor($lease);

            foreach ($recipients as $user) {
                if (! $user->wantsLeaseEndReminder()) {
                    continue;
                }

                if (! $user->lease_end_reminder_email) {
                    continue;
                }

                $prefDays = (int) ($user->lease_end_reminder_days ?? 30);
                // Sadece tercih edilen gün sayısında bir kez dene (günlük spam önlemi).
                if ($daysLeft !== $prefDays) {
                    continue;
                }

                try {
                    Mail::to($user->email)->send(
                        new LeaseEndingSoonMail($lease, $user, $daysLeft)
                    );
                    $sent++;
                } catch (\Throwable) {
                    // SMTP yoksa sessiz geç.
                }
            }
        }

        $this->info("{$sent} hatırlatma e-postası denendi.");

        return self::SUCCESS;
    }

    /**
     * @return list<User>
     */
    private function recipientsFor(Lease $lease): array
    {
        $users = [];

        if ($lease->tenant) {
            $users[] = $lease->tenant;
        }

        if ($lease->landlord_customer_id) {
            $landlordUser = User::query()
                ->where('role', User::ROLE_LANDLORD)
                ->where('customer_id', $lease->landlord_customer_id)
                ->first();

            if ($landlordUser) {
                $users[] = $landlordUser;
            }
        }

        return $users;
    }
}
