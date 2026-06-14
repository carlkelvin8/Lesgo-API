<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(
        private AuthenticationService $authService,
    ) {}

    public function isPermanentlyDeleted(User $user): bool
    {
        if (Schema::hasColumn('users', 'deleted_at') && $user->deleted_at !== null) {
            return true;
        }

        return str_ends_with(strtolower((string) $user->email), '@deleted.local');
    }

    /**
     * Permanently delete a customer account by erasing PII and revoking access.
     *
     * @throws ValidationException
     */
    public function permanentlyDeleteCustomer(User $user, ?string $reason = null): void
    {
        if (!$user->isCustomer()) {
            throw ValidationException::withMessages([
                'account' => ['Only customer accounts can be permanently deleted from the app.'],
            ]);
        }

        if ($this->isPermanentlyDeleted($user)) {
            throw ValidationException::withMessages([
                'account' => ['This account has already been deleted.'],
            ]);
        }

        if (Order::query()->forCustomer($user->id)->active()->exists()) {
            throw ValidationException::withMessages([
                'account' => [
                    'You have active orders. Please complete or cancel them before deleting your account.',
                ],
            ]);
        }

        $wallet = $user->wallet;
        if ($wallet && (float) $wallet->balance > 0) {
            throw ValidationException::withMessages([
                'account' => [
                    'Please withdraw or use your LesPay wallet balance before deleting your account.',
                ],
            ]);
        }

        DB::transaction(function () use ($user, $reason) {
            $this->purgePersonalData($user);
            $this->anonymizeUserRecord($user, $reason);
            $this->authService->revokeAllTokens($user);
        });

        AuditLogger::logAuth('account_permanently_deleted', $user->id, true);
    }

    /**
     * Permanently delete a driver account by erasing PII and revoking access.
     *
     * @throws ValidationException
     */
    public function permanentlyDeleteDriver(User $user, ?string $reason = null): void
    {
        if (!$user->isDriver()) {
            throw ValidationException::withMessages([
                'account' => ['Only driver accounts can use driver account deletion.'],
            ]);
        }

        if ($this->isPermanentlyDeleted($user)) {
            throw ValidationException::withMessages([
                'account' => ['This account has already been deleted.'],
            ]);
        }

        $driverProfileId = optional($user->driverProfile)->id;
        if ($driverProfileId) {
            $hasActiveOrders = Order::query()
                ->where('driver_id', $driverProfileId)
                ->active()
                ->exists();

            if ($hasActiveOrders) {
                throw ValidationException::withMessages([
                    'account' => [
                        'You have active deliveries. Please complete or hand off them before deleting your account.',
                    ],
                ]);
            }
        }

        $wallet = $user->wallet;
        if ($wallet && (float) $wallet->balance > 0) {
            throw ValidationException::withMessages([
                'account' => [
                    'Please withdraw your LesPay wallet balance before deleting your account.',
                ],
            ]);
        }

        DB::transaction(function () use ($user, $reason) {
            $this->purgeDriverData($user);
            $this->purgePersonalData($user);
            $this->anonymizeUserRecord($user, $reason);
            $this->authService->revokeAllTokens($user);
        });

        AuditLogger::logAuth('account_permanently_deleted', $user->id, true);
    }

    private function purgeDriverData(User $user): void
    {
        if ($user->driverProfile) {
            $user->driverProfile()->delete();
        }

        if (method_exists($user, 'driverLocations')) {
            $user->driverLocations()->delete();
        }
    }

    private function purgePersonalData(User $user): void
    {
        MediaStorageService::deleteIfExists($user->getRawOriginal('profile_photo_url'));
        MediaStorageService::deleteIfExists($user->getRawOriginal('profile_picture'));

        $user->addresses()->delete();
        $user->customerProfile()->delete();

        if (method_exists($user, 'reviews')) {
            $user->reviews()->delete();
        }

        if (method_exists($user, 'chatMessages')) {
            $user->chatMessages()->delete();
        }

        if (method_exists($user, 'supportTickets')) {
            $user->supportTickets()->delete();
        }

        if (method_exists($user, 'socialShares')) {
            $user->socialShares()->delete();
        }

        if (method_exists($user, 'documentVerifications')) {
            $user->documentVerifications()->delete();
        }

        if (method_exists($user, 'userSessions')) {
            $user->userSessions()->delete();
        }

        if (method_exists($user, 'geofenceEvents')) {
            $user->geofenceEvents()->delete();
        }

        if (method_exists($user, 'websocketConnections')) {
            $user->websocketConnections()->delete();
        }

        if (method_exists($user, 'realtimeNotifications')) {
            $user->realtimeNotifications()->delete();
        }

        $user->refreshTokens()->delete();
    }

    private function anonymizeUserRecord(User $user, ?string $reason): void
    {
        $payload = [
            'name'              => 'Deleted User',
            'email'             => 'deleted_' . $user->id . '_' . Str::lower(Str::random(10)) . '@deleted.local',
            'phone_number'      => null,
            'google_id'         => null,
            'address_line1'     => null,
            'address_line2'     => null,
            'profile_photo_url' => null,
            'profile_picture'   => null,
            'date_of_birth'     => null,
            'fcm_token'         => null,
            'password'          => Hash::make(Str::random(64)),
            'remember_token'    => null,
            'referral_code'     => null,
            'referred_by'       => null,
            'points'            => 0,
        ];

        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = false;
        }

        if (Schema::hasColumn('users', 'deactivated_at')) {
            $payload['deactivated_at'] = now();
        }

        if (Schema::hasColumn('users', 'deactivation_reason')) {
            $payload['deactivation_reason'] = $reason ?: 'Permanent account deletion';
        }

        if (Schema::hasColumn('users', 'deleted_at')) {
            $payload['deleted_at'] = now();
        }

        $user->update($payload);
    }
}
