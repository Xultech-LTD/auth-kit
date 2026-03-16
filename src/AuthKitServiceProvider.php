<?php

namespace Xul\AuthKit;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Contracts\TwoFactorDriverContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;
use Xul\AuthKit\Listeners\SendEmailVerificationNotification;
use Xul\AuthKit\Listeners\SendPasswordResetNotification;
use Xul\AuthKit\RateLimiting\RateLimitingServiceProviderMixin;
use Xul\AuthKit\Support\CacheTokenRepository;
use Xul\AuthKit\Support\PendingEmailVerification;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\PendingPasswordReset;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;
use Xul\AuthKit\Contracts\Forms\FieldComponentResolverContract;
use Xul\AuthKit\Contracts\Forms\FieldOptionsResolverContract;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Support\Resolvers\FieldComponentResolver;
use Xul\AuthKit\Support\Resolvers\FieldOptionsResolver;
use Xul\AuthKit\Support\Resolvers\FormSchemaResolver;

final class AuthKitServiceProvider extends ServiceProvider
{
    use RateLimitingServiceProviderMixin;

    /**
     * Register package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/authkit.php', 'authkit');

        $this->registerAuthKitRateLimiting();

        $this->app->singleton(TokenRepositoryContract::class, function ($app) {
            return new CacheTokenRepository($app['cache']->store());
        });

        $this->app->singleton(PendingLogin::class, function ($app) {
            return new PendingLogin($app->make(TokenRepositoryContract::class));
        });

        $this->app->singleton(PendingEmailVerification::class, function ($app) {
            return new PendingEmailVerification(
                $app->make(TokenRepositoryContract::class),
                $app['cache']->store(),
                $app['auth']
            );
        });

        $this->app->singleton(VerifyEmailLinkAction::class, function ($app) {
            return new VerifyEmailLinkAction(
                $app->make(PendingEmailVerification::class),
                $app->make(AuthFactory::class)
            );
        });

        $this->app->singleton(PendingPasswordReset::class, function ($app) {
            return new PendingPasswordReset(
                $app->make(TokenRepositoryContract::class),
                $app['cache']->store()
            );
        });

        $this->app->singleton(PasswordResetNotifierContract::class, function ($app) {
            $class = (string) config(
                'authkit.password_reset.delivery.notifier',
                \Xul\AuthKit\Support\Notifiers\PasswordResetNotifier::class
            );

            if ($class === '') {
                throw new InvalidArgumentException('AuthKit: password reset notifier class is empty.');
            }

            $instance = $app->make($class);

            if (! $instance instanceof PasswordResetNotifierContract) {
                throw new InvalidArgumentException(sprintf(
                    'AuthKit: password reset notifier [%s] must implement %s.',
                    $class,
                    PasswordResetNotifierContract::class
                ));
            }

            return $instance;
        });

        $this->app->singleton(PasswordResetUrlGeneratorContract::class, function ($app) {
            $class = (string) config(
                'authkit.password_reset.url_generator',
                \Xul\AuthKit\Support\PasswordReset\PasswordResetUrlGenerator::class
            );

            if ($class === '') {
                throw new InvalidArgumentException('AuthKit: password reset url generator class is empty.');
            }

            $instance = $app->make($class);

            if (! $instance instanceof PasswordResetUrlGeneratorContract) {
                throw new InvalidArgumentException(sprintf(
                    'AuthKit: password reset url generator [%s] must implement %s.',
                    $class,
                    PasswordResetUrlGeneratorContract::class
                ));
            }

            return $instance;
        });

        $this->app->singleton(PasswordResetPolicyContract::class, function ($app) {
            $class = (string) config(
                'authkit.password_reset.policy',
                \Xul\AuthKit\Support\PasswordReset\PermissivePasswordResetPolicy::class
            );

            if ($class === '') {
                throw new InvalidArgumentException('AuthKit: password reset policy class is empty.');
            }

            $instance = $app->make($class);

            if (! $instance instanceof PasswordResetPolicyContract) {
                throw new InvalidArgumentException(sprintf(
                    'AuthKit: password reset policy [%s] must implement %s.',
                    $class,
                    PasswordResetPolicyContract::class
                ));
            }

            return $instance;
        });

        $this->app->singleton(PasswordResetUserResolverContract::class, function ($app) {
            $strategy = (string) config('authkit.password_reset.user_resolver.strategy', 'provider');

            if ($strategy === 'custom') {
                $class = (string) config('authkit.password_reset.user_resolver.resolver_class');

                if ($class === '') {
                    throw new InvalidArgumentException('AuthKit: password reset custom user resolver class is empty.');
                }

                $instance = $app->make($class);

                if (! $instance instanceof PasswordResetUserResolverContract) {
                    throw new InvalidArgumentException(sprintf(
                        'AuthKit: password reset user resolver [%s] must implement %s.',
                        $class,
                        PasswordResetUserResolverContract::class
                    ));
                }

                return $instance;
            }

            $instance = $app->make(\Xul\AuthKit\Support\PasswordReset\ProviderPasswordResetUserResolver::class);

            if (! $instance instanceof PasswordResetUserResolverContract) {
                throw new InvalidArgumentException(sprintf(
                    'AuthKit: password reset provider user resolver must implement %s.',
                    PasswordResetUserResolverContract::class
                ));
            }

            return $instance;
        });

        $this->app->singleton(PasswordUpdaterContract::class, function ($app) {
            $class = config('authkit.password_reset.password_updater.class');

            if (! is_string($class) || trim($class) === '') {
                $class = \Xul\AuthKit\Support\PasswordReset\DefaultPasswordUpdater::class;
            }

            $instance = $app->make($class);

            if (! $instance instanceof PasswordUpdaterContract) {
                throw new InvalidArgumentException(sprintf(
                    'AuthKit: password updater [%s] must implement %s.',
                    $class,
                    PasswordUpdaterContract::class
                ));
            }

            return $instance;
        });

        $this->app->singleton(EmailVerificationNotifierContract::class, function ($app) {
            $class = (string) config(
                'authkit.email_verification.delivery.notifier',
                \Xul\AuthKit\Support\Notifiers\EmailVerificationNotifier::class
            );

            if ($class === '') {
                throw new InvalidArgumentException('AuthKit: email verification notifier class is empty.');
            }

            $instance = $app->make($class);

            if (! $instance instanceof EmailVerificationNotifierContract) {
                throw new InvalidArgumentException(sprintf(
                    'AuthKit: email verification notifier [%s] must implement %s.',
                    $class,
                    EmailVerificationNotifierContract::class
                ));
            }

            return $instance;
        });

        $this->app->singleton(FieldOptionsResolverContract::class, function ($app) {
            return new FieldOptionsResolver();
        });

        $this->app->singleton(FieldComponentResolverContract::class, function ($app) {
            return new FieldComponentResolver();
        });

        $this->app->singleton(FormSchemaResolverContract::class, function ($app) {
            return new FormSchemaResolver(
                $app->make(FieldOptionsResolverContract::class),
                $app->make(FieldComponentResolverContract::class),
            );
        });

        $this->app->singleton(TwoFactorManager::class, function ($app) {
            return new TwoFactorManager($app);
        });

        $this->app->bind(TwoFactorDriverContract::class, function ($app) {
            return $app->make(TwoFactorManager::class)->driver();
        });
    }

    /**
     * Bootstrap package services.
     *
     * @return void
     * @throws \Throwable
     */
    public function boot(): void
    {
        $this->bootAuthKitRateLimiting();

        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');

        if ((bool) config('authkit.app.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/app-web.php');
            $this->loadRoutesFrom(__DIR__ . '/Routes/app-api.php');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'authkit');

        Blade::anonymousComponentNamespace('authkit::components', 'authkit');

        $this->registerEmailVerificationListener();
        $this->registerPasswordResetListener();

        $this->registerPublishables();

        Password::defaults(function () {
            $rule = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->isProduction()
                ? $rule->mixedCase()->uncompromised()
                : $rule;
        });
    }

    /**
     * Register the email verification delivery listener (optional).
     *
     * @return void
     */
    private function registerEmailVerificationListener(): void
    {
        if (! (bool) config('authkit.email_verification.delivery.use_listener', true)) {
            return;
        }

        $listener = (string) config(
            'authkit.email_verification.delivery.listener',
            SendEmailVerificationNotification::class
        );

        if ($listener === '') {
            throw new InvalidArgumentException('AuthKit: email verification delivery listener class is empty.');
        }

        if (! class_exists($listener)) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: email verification delivery listener [%s] does not exist.',
                $listener
            ));
        }

        Event::listen(AuthKitEmailVerificationRequired::class, $listener);
    }

    /**
     * Register the password reset delivery listener (optional).
     *
     * @return void
     */
    private function registerPasswordResetListener(): void
    {
        if (! (bool) config('authkit.password_reset.delivery.use_listener', true)) {
            return;
        }

        $listener = (string) config(
            'authkit.password_reset.delivery.listener',
            SendPasswordResetNotification::class
        );

        if ($listener === '') {
            throw new InvalidArgumentException('AuthKit: password reset delivery listener class is empty.');
        }

        if (! class_exists($listener)) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: password reset delivery listener [%s] does not exist.',
                $listener
            ));
        }

        Event::listen(AuthKitPasswordResetRequested::class, $listener);
    }

    /**
     * Register publishable package assets.
     *
     * @return void
     */
    private function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/authkit.php' => config_path('authkit.php'),
        ], 'authkit-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/authkit'),
        ], 'authkit-views');

        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/authkit'),
        ], 'authkit-assets');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'authkit-migrations');

        $this->publishes([
            __DIR__ . '/Routes/web.php' => base_path('routes/authkit-web.php'),
            __DIR__ . '/Routes/api.php' => base_path('routes/authkit-api.php'),
            __DIR__ . '/Routes/app-web.php' => base_path('routes/authkit-app-web.php'),
            __DIR__ . '/Routes/app-api.php' => base_path('routes/authkit-app-api.php'),
        ], 'authkit-routes');
    }
}