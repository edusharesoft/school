<?php

namespace App\Http\Controllers\Branch\Website;

use App\Http\Controllers\Central\Website\PostController as CentralPostController;

/** News & notices of this branch's own public website (same screens as the institution site, scoped to the branch). */
class PostController extends CentralPostController
{
    protected string $layout = 'layouts.branch';

    protected string $routePrefix = 'branch.website.';

    protected function siteBranchId(): ?int
    {
        return tenant()->branchId();
    }
}
