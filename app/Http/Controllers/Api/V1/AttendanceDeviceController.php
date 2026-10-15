<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\DevicePunchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST https://<branch-host>/api/v1/attendance/punches
 * Header:  X-Device-Key: dev_…   (issued in Attendance → Settings → Devices; only its hash is stored)
 * Body:    {"punches":[{"code":"BR01-E0003","time":"2026-09-28 07:52:10"}, …]}   (Asia/Dhaka local time)
 *
 * The branch is taken from the host, and the key must belong to an active device of that branch, so a
 * device can never write into another branch. Re-sent punches are ignored (idempotent).
 */
class AttendanceDeviceController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, DevicePunchService $service): JsonResponse
    {
        $key = (string) $request->header('X-Device-Key');
        $device = $key !== '' ? AttendanceDevice::query()->where('api_key_hash', AttendanceDevice::hashKey($key))->first() : null;

        if (! $device || ! $device->is_active) {
            return $this->fail(__('Unknown or disabled device.'), 401);
        }

        $data = $request->validate([
            'punches' => ['required', 'array', 'min:1', 'max:500'],
            'punches.*.code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'punches.*.time' => ['required', 'date', 'before_or_equal:+5 minutes', 'after:-45 days'],
        ]);

        $summary = $service->ingest($device, $data['punches']);

        return $this->ok($summary, __('Punches processed.'));
    }
}
