<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:create {email} {--name=} {--role=staff} {--password=}', function (string $email) {
    $role = Role::tryFrom($this->option('role'));
    if (! $role) {
        $this->error('Role must be one of: '.implode(', ', array_column(Role::cases(), 'value')));

        return 1;
    }

    $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first('email'));

        return 1;
    }

    $password = $this->option('password') ?: Str::password(20);
    $user = new User([
        'name' => $this->option('name') ?: Str::before($email, '@'),
        'email' => $email,
        'password' => $password,
    ]);
    $user->role = $role;
    $user->save();

    $this->info("Created {$role->value} user #{$user->id} <{$user->email}>");
    if (! $this->option('password')) {
        $this->line("Generated password: {$password}");
    }

    return 0;
})->purpose('Create a user with a given role');

Artisan::command('user:token {email} {--name=cli} {--expires= : Minutes until expiry; omit for a non-expiring token}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with email {$email}");

        return 1;
    }

    $expires = $this->option('expires');
    if ($expires !== null && (! ctype_digit($expires) || (int) $expires < 1)) {
        $this->error('--expires must be a positive number of minutes');

        return 1;
    }

    $token = $user->createToken(
        $this->option('name'),
        expiresAt: $expires === null ? null : now()->addMinutes((int) $expires),
    );
    $this->line($token->plainTextToken);

    return 0;
})->purpose('Issue a Sanctum token for a user (printed once)');

Artisan::command('service:bootstrap {email} {--token-file=} {--seed}', function (string $email) {
    if ($this->option('seed')) {
        $this->call('db:seed', ['--force' => true]);
    }

    $user = User::firstOrNew(['email' => $email]);
    if (! $user->exists) {
        $user->fill(['name' => Str::before($email, '@'), 'password' => Str::password(32)]);
        $user->role = Role::Service;
        $user->save();
        $this->info("Created service user <{$email}>");
    } elseif ($user->role !== Role::Service) {
        $this->error("<{$email}> exists but is {$user->role->value}, not service");

        return 1;
    }

    $file = $this->option('token-file');
    if ($file && is_file($file) && trim((string) file_get_contents($file)) !== '') {
        $this->info("Token file {$file} already present; leaving it");

        return 0;
    }

    $token = $user->createToken('service-bootstrap')->plainTextToken;
    if (! $file) {
        $this->line($token);

        return 0;
    }

    file_put_contents($file, $token.PHP_EOL);
    chmod($file, 0644);
    $this->info("Wrote service token to {$file}");

    return 0;
})->purpose('Idempotently ensure a service user exists and issue it a token (for container setup)');

// Delete tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
