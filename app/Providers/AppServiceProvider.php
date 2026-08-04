<?php

namespace App\Providers;

use App\Auth\MigratingUserProvider;
use App\Models\EmailConfiguration;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		Auth::provider('migrating', function ($app, array $config) {
			return new MigratingUserProvider($app['hash'], $config['model']);
		});

		Gate::define('perm', function ($user, string $permissionCode) {
			return $user->hasPermission($permissionCode);
		});

		$this->applyEmailConfiguration();

		User::observe(UserObserver::class);
		Vite::prefetch(concurrency: 3);
		Inertia::share([
			'locale' => fn() => app()->getLocale(),
			'translations' => fn() => [
				'ui' => trans('ui'),
			],
			'flash' => fn() => [
				'success' => session('success'),
				'error' => session('error'),
				'id' => session()->has('success') || session()->has('error')
					? (string) Str::uuid()
					: null,
			],
		]);
	}

	private function applyEmailConfiguration(): void
	{
		if (!Schema::hasTable('email_configurations')) {
			return;
		}

		$config = EmailConfiguration::query()
			->where('is_active', true)
			->first();

		if (! $config) {
			return;
		}

		Config::set('mail.default', $config->mailer ?: config('mail.default'));
		Config::set('mail.mailers.smtp.host', $config->host);
		Config::set('mail.mailers.smtp.port', $config->port);
		Config::set('mail.mailers.smtp.username', $config->username);
		Config::set('mail.mailers.smtp.password', $config->password);
		Config::set('mail.mailers.smtp.encryption', $config->encryption ?: null);
		Config::set('mail.from.address', $config->from_address ?: config('mail.from.address'));
		Config::set('mail.from.name', $config->from_name ?: config('mail.from.name'));
	}
}
