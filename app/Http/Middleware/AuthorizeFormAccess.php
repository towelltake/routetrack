<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeFormAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $formName = $this->resolveFormName($routeName);

        if ($formName === null) {
            return $next($request);
        }

        $action = $this->resolveAction($request, $routeName);

        if (!$user->hasFormPermission($formName, $action)) {
            return $this->deny($request, $formName, $action);
        }

        return $next($request);
    }

    private function deny(Request $request, string $formName, string $action): Response
    {
        $label = match ($action) {
            'write' => 'edit',
            default => $action,
        };

        $message = sprintf(
            'You do not have %s permission for %s.',
            $label,
            $formName
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        if ($request->headers->get('X-Inertia')) {
            return back(303)->with('error', $message);
        }

        return redirect()->route('customer-location.index')->with('error', $message);
    }

    private function resolveFormName(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        foreach (config('form_permissions.routes', []) as $prefix => $formName) {
            if (str_starts_with($routeName, $prefix)) {
                return $formName;
            }
        }

        return null;
    }

    private function resolveAction(Request $request, ?string $routeName): string
    {
        if ($routeName) {
            if (str_ends_with($routeName, '.destroy')) {
                return 'delete';
            }

            if (str_ends_with($routeName, '.store')) {
                return 'create';
            }

            if (str_ends_with($routeName, '.update') || str_ends_with($routeName, '.save')) {
                return 'write';
            }

            if (str_ends_with($routeName, '.edit')) {
                return 'write';
            }

            if (str_ends_with($routeName, '.create')) {
                return 'create';
            }

            if (str_ends_with($routeName, '.show')) {
                return 'view';
            }

            if (str_ends_with($routeName, '.index') || str_ends_with($routeName, '.load')) {
                return 'read';
            }
        }

        return match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'write',
            'DELETE' => 'delete',
            default => 'read',
        };
    }
}
