<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateSuperAdministrator extends Command
{
    protected $signature = 'platform:create-super-admin {email? : Email address for the administrator} {--promote-existing : Explicitly promote an active, verified account after an interactive confirmation}';

    protected $description = 'Create or explicitly promote a Commonwealth super administrator without storing a default password';

    public function handle(): int
    {
        $role = Role::query()->where('slug', 'super_administrator')->first();

        if (! $role) {
            $this->error('Administrator roles are missing. Run the role and permission seeder first.');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?: $this->takeEnvironmentValue('INITIAL_ADMIN_EMAIL');
        if (! $email && $this->input->isInteractive()) {
            $email = $this->ask('Administrator email');
        }
        $email = strtolower(trim((string) $email));

        if (strlen($email) > 190 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address that is no longer than 190 characters.');

            return self::INVALID;
        }

        $existing = User::query()->with('role')->where('email', $email)->first();

        if ($existing?->role?->slug === 'super_administrator') {
            $this->info('A super administrator already exists for that email. No changes were made.');

            return self::SUCCESS;
        }

        if ($existing) {
            return $this->promoteExisting($existing, $role);
        }

        $name = $this->takeEnvironmentValue('INITIAL_ADMIN_NAME');
        if (! $name && $this->input->isInteractive()) {
            $name = $this->ask('Administrator name');
        }
        $name = trim((string) $name);

        if ($name === '' || strlen($name) > 120) {
            $this->error('Enter an administrator name that is no longer than 120 characters.');

            return self::INVALID;
        }

        $password = $this->requestPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password, $role): User {
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->password = $password;
            $user->forceFill([
                'role_id' => $role->id,
                'status' => 'active',
                'is_demo' => false,
                'email_verified_at' => now(),
            ]);
            $user->save();

            $this->recordProvisioningAudit($user, 'admin.account.provisioned');

            return $user;
        });

        unset($password);

        $this->info("Super administrator created for {$user->email}.");
        $this->warn('Store the password in your password manager. It was not written to application output or source files.');

        return self::SUCCESS;
    }

    private function promoteExisting(User $existing, Role $role): int
    {
        if (! $this->option('promote-existing')) {
            $this->error('That email belongs to an existing account. To use the same verified identity, rerun with --promote-existing from an interactive operator session.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Promoting an existing account requires an interactive operator confirmation.');

            return self::FAILURE;
        }

        if (! $existing->hasVerifiedEmail() || $existing->status !== 'active') {
            $this->error('Only an active account with a verified email can be promoted.');

            return self::FAILURE;
        }

        if (! $this->confirm("Promote {$existing->email} to super administrator and replace its password?", false)) {
            $this->warn('No changes were made.');

            return self::FAILURE;
        }

        $password = $this->requestPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $promoted = DB::transaction(function () use ($existing, $role, $password): bool {
                $user = User::query()->lockForUpdate()->findOrFail($existing->id);

                if (! $user->hasVerifiedEmail() || $user->status !== 'active') {
                    throw new RuntimeException('The account is no longer active and verified; no changes were made.');
                }

                if ($user->role_id === $role->id) {
                    return false;
                }

                $previousRole = $user->role?->slug;
                $user->forceFill([
                    'role_id' => $role->id,
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->recordProvisioningAudit($user, 'admin.role.promoted', [
                    'previous_role' => $previousRole,
                ]);

                return true;
            });
        } catch (RuntimeException $exception) {
            unset($password);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        unset($password);

        $this->info($promoted
            ? "Verified account {$existing->email} was promoted to super administrator."
            : 'The account was already promoted. No changes were made.');
        $this->warn('The existing investor profile and account records were kept. The new password was not written to output or source files.');

        return self::SUCCESS;
    }

    private function requestPassword(): ?string
    {
        if ($this->input->isInteractive()) {
            $password = (string) $this->secret('Choose a unique password (at least 16 characters)');
            $confirmation = (string) $this->secret('Confirm the password');

            if (strlen($password) < 16 || ! hash_equals($password, $confirmation)) {
                $this->error('The password must be at least 16 characters, and both entries must match.');

                return null;
            }

            return $password;
        }

        $password = $this->takeEnvironmentValue('INITIAL_ADMIN_PASSWORD');

        if ($password === null || strlen($password) < 16) {
            $this->error('Non-interactive provisioning requires INITIAL_ADMIN_PASSWORD with at least 16 characters, supplied through the runtime secret store.');

            return null;
        }

        return $password;
    }

    private function takeEnvironmentValue(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? false;
        }

        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);

        return $value === false || $value === '' ? null : (string) $value;
    }

    private function recordProvisioningAudit(User $user, string $action, array $properties = []): void
    {
        AuditLog::query()->create([
            'actor_id' => null,
            'action' => $action,
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->getKey(),
            'request_id' => (string) Str::uuid(),
            'ip_address' => null,
            'user_agent' => 'artisan',
            'properties' => array_merge([
                'source' => 'artisan',
                'role' => 'super_administrator',
            ], $properties),
            'created_at' => now(),
        ]);
    }
}
