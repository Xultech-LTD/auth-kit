<?php

namespace Xul\AuthKit\Http\Controllers\Web\App;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Xul\AuthKit\Support\App\ResolveSecurityPageData;

/**
 * SecurityViewController
 *
 * Renders the authenticated security page.
 */
final class SecurityViewController
{
    public function __construct(
        protected ResolveSecurityPageData $resolver
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $page = (array) data_get(config('authkit.app', []), 'pages.security', []);

        $title = (string) ($page['title'] ?? 'Security');
        $heading = (string) ($page['heading'] ?? 'Security settings');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.security');
        $sections = (array) ($page['sections'] ?? []);

        $guard = (string) config('authkit.auth.guard', 'web');
        $user = auth($guard)->user();

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => 'security',
            'currentPage' => 'security',
            'sections' => $sections,
            ...$this->resolver->resolve($user),
        ]);
    }
}