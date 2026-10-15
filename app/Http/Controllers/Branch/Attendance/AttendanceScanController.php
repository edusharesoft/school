<?php

namespace App\Http\Controllers\Branch\Attendance;

use App\Http\Controllers\Controller;
use App\Services\Attendance\DevicePunchService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gate / front-desk scanning station. Works with USB / Bluetooth barcode & QR scanners (they type the
 * ID and press Enter) and, where the browser supports it, the device camera (BarcodeDetector API).
 * Students → today's daily attendance; employees → check-in, then check-out.
 */
class AttendanceScanController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeScan($request);

        return view('branch.attendance.scan', [
            'canStudents' => $request->user()->can('student-attendance.mark'),
            'canStaff' => $request->user()->can('staff-attendance.mark'),
        ]);
    }

    public function store(Request $request, DevicePunchService $punches): JsonResponse
    {
        $this->authorizeScan($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'method' => ['nullable', 'in:qr,id_card'],
        ]);

        $code = strtoupper(trim($data['code']));
        $method = $data['method'] ?? 'qr';
        $user = $request->user();

        // Each half of the scanner needs its own permission — checked before anything is written.
        [$type] = $punches->identify($code);
        if (($type === 'student' && ! $user->can('student-attendance.mark')) || ($type === 'employee' && ! $user->can('staff-attendance.mark'))) {
            return response()->json(['ok' => false, 'state' => 'forbidden', 'message' => __('You are not allowed to mark this person.')], 403);
        }

        [$type, $person, $outcome] = $punches->apply($code, CarbonImmutable::now(), $method);
        if ($type === 'student' && isset($outcome['record']) && $outcome['state'] === 'marked') {
            $outcome['record']->forceFill(['marked_by' => $user->id])->save();
        }

        return response()->json([
            'ok' => $outcome['ok'],
            'state' => $outcome['state'],
            'message' => $outcome['message'],
            'person' => $person ? ['type' => $type, 'name' => $person->name, 'code' => $code] : null,
            'status' => isset($outcome['record']) ? $outcome['record']->status->label() : null,
            'time' => now()->format('H:i:s'),
        ], $person ? 200 : 404);
    }

    protected function authorizeScan(Request $request): void
    {
        abort_unless($request->user()->can('student-attendance.mark') || $request->user()->can('staff-attendance.mark'), 403);
    }
}
