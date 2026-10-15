<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Controllers whose pages are served both in the branch back-office (routes "branch.*") and in the
 * teacher portal (routes "portal.teacher.*"). Views receive $layout and $routePrefix.
 */
trait SharedWithTeacherPortal
{
    protected function layout(Request $request): string
    {
        return $this->inTeacherPortal($request) ? 'layouts.portal' : 'layouts.branch';
    }

    protected function routePrefix(Request $request): string
    {
        return $this->inTeacherPortal($request) ? 'portal.teacher.' : 'branch.';
    }

    protected function inTeacherPortal(Request $request): bool
    {
        return str_starts_with((string) $request->route()?->getName(), 'portal.teacher.');
    }
}
