<?php

namespace App\Providers;

use App\Auth\SfaUserProvider;
use Illuminate\Support\Facades\Auth;
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
		Auth::provider('sfa', function ($app, array $config) {
			return new SfaUserProvider($config['model']);
		});

		Gate::define('perm', function ($user, string $permissionCode) {
			return $user->hasPermission($permissionCode);
		});

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

}
