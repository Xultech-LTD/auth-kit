<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

it('renders the security page for authenticated users', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');
    $guard = (string) config('authkit.auth.guard', 'web');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'name' => 'Michael',
            'email' => 'michael@example.com',
            'two_factor_enabled' => true,
            'two_factor_methods' => ['totp'],
            'two_factor_recovery_codes' => ['hashed-code-placeholder'],
        ];

        public function hasTwoFactorEnabled(): bool
        {
            return true;
        }

        public function twoFactorMethods(): array
        {
            return ['totp'];
        }

        public function twoFactorRecoveryCodes(): array
        {
            return ['hashed-code-placeholder'];
        }
    };

    $this->actingAs($user, $guard)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Security')
        ->assertSee('Two-factor authentication')
        ->assertSee('Sessions')
        ->assertSee('Update password')
        ->assertSee('Current password')
        ->assertSee('New password')
        ->assertSee('Confirm new password');
});