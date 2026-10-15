<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use ApiResponse;

    /** Central host: branches the user may see. Branch host: only the current branch. */
    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        $query = $tenant->isBranch()
            ? Branch::query()->whereKey($tenant->branchId())
            : Branch::query()->visibleTo($request->user());

        $branches = $query
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->ordered()
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok(BranchResource::collection($branches));
    }

    public function show(Request $request, Branch $branch, TenantContext $tenant): JsonResponse
    {
        if ($tenant->isBranch() && $branch->id !== $tenant->branchId()) {
            abort(404);
        }
        abort_unless($request->user()->can('view', $branch) || $branch->id === $tenant->branchId(), 404);

        return $this->ok(new BranchResource($branch));
    }
}
