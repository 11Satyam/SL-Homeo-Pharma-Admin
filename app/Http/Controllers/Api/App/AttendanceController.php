<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceConflict;
use App\Services\AttendanceService;
use App\Support\BusinessDay;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends FieldController
{
    public function __construct(private AttendanceService $attendance) {}

    public function today(Request $r)
    {
        return response()->json(['data' => $this->state($this->attendance->today($r->user()))]);
    }

    public function checkIn(Request $r)
    {
        return $this->mark($r, fn ($d) => $this->attendance->checkIn($r->user(), $d), 201);
    }

    public function checkOut(Request $r)
    {
        return $this->mark($r, fn ($d) => $this->attendance->checkOut($r->user(), $d), 200);
    }

    /** Own history only; administrators review everyone's in the Admin Panel. */
    public function history(Request $r)
    {
        $p = $this->listParams($r);
        $q = Attendance::where('user_id', $this->uid())
            ->when($p['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($p['to'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderByDesc('date');

        return AttendanceResource::collection($q->paginate($p['per_page'] ?? 31)->withQueryString());
    }

    private function mark(Request $r, \Closure $action, int $status)
    {
        $d = $r->validate([
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'accuracy_m' => 'nullable|numeric|min:0|max:100000',
            'client_timestamp' => 'nullable|date', 'remarks' => 'nullable|string|max:500',
        ]);
        if ((float) $d['latitude'] === 0.0 && (float) $d['longitude'] === 0.0) {
            // 0,0 is the classic "no fix" placeholder from devices, not a real location.
            throw ValidationException::withMessages(['latitude' => 'Location fix is not valid. Enable location and retry.']);
        }
        try {
            $a = $action($d);
        } catch (AttendanceConflict $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'attendance_conflict', 'data' => $this->state($e->attendance)], 409);
        }

        return response()->json(['data' => $this->state($a)], $status);
    }

    private function state(?Attendance $a): array
    {
        return [
            'date' => BusinessDay::today(), 'status' => $a?->status ?? 'not_checked_in',
            'record' => $a ? new AttendanceResource($a) : null,
            'geofence' => $this->attendance->geofence(auth()->user()->distributor),
        ];
    }
}
