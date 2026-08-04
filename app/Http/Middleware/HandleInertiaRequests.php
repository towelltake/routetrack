<?php

namespace App\Http\Middleware;

use App\Support\AmountPrecision;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
	/**
	 * The root template that's loaded on the first page visit.
	 *
	 * @see https://inertiajs.com/server-side-setup#root-template
	 *
	 * @var string
	 */
	protected $rootView = 'app';

	/**
	 * Determines the current asset version.
	 *
	 * @see https://inertiajs.com/asset-versioning
	 */
	public function version(Request $request): ?string
	{
		return parent::version($request);
	}

	/**
	 * Define the props that are shared by default.
	 *
	 * @see https://inertiajs.com/shared-data
	 *
	 * @return array<string, mixed>
	 */
	public function share(Request $request): array
	{
		$formPermissions = $request->user()
			? rescue(fn () => $request->user()->formPermissions(), [])
			: [];

		return array_merge(parent::share($request), [
			'auth' => [
				'user' => $request->user() ? array_merge($request->user()->toArray(), [
					'gravatar' => $request->user()->gravatar,
				]) : null,
				'permissions' => array_keys(array_filter(
					$formPermissions,
					fn (array $permission) => $permission['all'] || $permission['view'] || $permission['read']
				)),
				'formPermissions' => $formPermissions,
			],
			'settings' => [
				'amountDecimalPlaces' => AmountPrecision::get(),
			],
		]);
	}
}
