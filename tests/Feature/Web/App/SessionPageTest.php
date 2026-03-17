<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('renders the sessions page for authenticated users', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');
    $guard = (string) config('authkit.auth.guard', 'web');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'id' => 1,
            'name' => 'Michael',
            'email' => 'michael@example.com',
        ];
    };

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => 1,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 Chrome/122.0 Windows NT 10.0 Win64 x64',
        'payload' => base64_encode('test'),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user, $guard)
        ->withSession(['_token' => csrf_token()])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Sessions')
        ->assertSee('Active sessions')
        ->assertSee('127.0.0.1')
        ->assertSee('Chrome', false);
});