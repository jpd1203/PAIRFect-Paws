<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class ProvisionResearchAdministrator extends Command
{
    private const DATABASE = 'pairfect_research_survey01';

    private const CATALOG_COUNT = 70;

    protected $signature = 'research:provision-admin';

    protected $description = 'Interactively create the first administrator for research survey 01';

    public function handle(): int
    {
        if (! app()->environment('production') || config('app.env') !== 'production' || config('research.enabled') !== true) {
            $this->error('Refusing to provision: production environment and enabled Research Mode are required.');

            return self::FAILURE;
        }

        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== self::DATABASE
            || $db->selectOne('select database() as name', [], false)?->name !== self::DATABASE) {
            $this->error('Refusing to provision: the active MySQL database must be '.self::DATABASE.'.');

            return self::FAILURE;
        }

        $schema = $db->getSchemaBuilder();
        if (! $schema->hasTable('research_catalog') || ! $schema->hasTable('research_placements')
            || ! $schema->hasColumn('research_placements', 'status')
            || $db->table('research_catalog')->useWritePdo()->count() !== self::CATALOG_COUNT) {
            $this->error('Refusing to provision: initialize the shared research catalog before creating an administrator.');

            return self::FAILURE;
        }

        $users = User::on($db->getName())->useWritePdo();
        if ((clone $users)->where('role', Role::Administrator->value)->exists()) {
            $this->error('An administrator already exists. This command is for first-time provisioning only.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('An interactive SSH terminal with hidden password input is required.');

            return self::FAILURE;
        }

        $details = [
            'first_name' => trim((string) $this->ask('First name')),
            'last_name' => trim((string) $this->ask('Last name')),
            'email' => mb_strtolower(trim((string) $this->ask('Email address'))),
        ];
        $validation = Validator::make($details, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);
        if ($validation->fails()) {
            $this->error($validation->errors()->first());

            return self::FAILURE;
        }
        if ((clone $users)->where('email', $details['email'])->exists()) {
            $this->error('That email is already in use. No existing account will be promoted or changed.');

            return self::FAILURE;
        }
        if (! $this->confirm('Have you independently confirmed that the named administrator controls this email address? This will mark it verified.', false)) {
            $this->error('Mailbox ownership was not confirmed. No account was created.');

            return self::FAILURE;
        }

        $password = $this->secret('Password (at least 16 characters, no more than 72 bytes)', false);
        $confirmation = $this->secret('Confirm password', false);
        if (! is_string($password) || ! is_string($confirmation) || ! hash_equals($password, $confirmation)
            || mb_strlen($password) < 16 || strlen($password) > 72 || trim($password) === ''
            || str_contains($password, "\0")) {
            $this->error('Passwords must match and meet the length and character requirements.');

            return self::FAILURE;
        }

        $hash = Hash::make($password);
        unset($password, $confirmation);

        try {
            $user = $db->transaction(function () use ($db, $users, $details, $hash): ?User {
                // Serialize first-account provisioning against the same initialized catalog row.
                $catalog = $db->table('research_catalog')->orderBy('pet_id')->lockForUpdate()->first();
                if (! $catalog || (clone $users)->where('role', Role::Administrator->value)->lockForUpdate()->first()
                    || (clone $users)->where('email', $details['email'])->lockForUpdate()->first()) {
                    return null;
                }

                $user = (clone $users)->create([
                    ...$details,
                    'role' => Role::Administrator,
                    'password' => $hash,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                AuditLogService::log(null, 'Research Administrator Provisioned', 'User', $user->id,
                    'Authorized SSH operator attested mailbox ownership for initial research administrator.');

                return $user;
            }, 3);
        } catch (\Throwable) {
            // Database errors can include INSERT bindings. Never print credentials or the hash.
            $this->error('Provisioning failed. Check the research administrator count before retrying.');

            return self::FAILURE;
        }

        if (! $user) {
            $this->error('An administrator or the requested email already exists, or the catalog changed. No account was created.');

            return self::FAILURE;
        }
        $this->info("Research administrator created. User ID: {$user->id}.");

        return self::SUCCESS;
    }
}
