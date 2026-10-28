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

// Delete tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
