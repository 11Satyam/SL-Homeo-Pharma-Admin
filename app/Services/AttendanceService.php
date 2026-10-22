<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Distributor;
use App\Models\User;
use App\Support\BusinessDay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Check-in/out against the server clock. Device coordinates are stored as reported
 * and compared to the distributor's geofence when one is configured; they are never
 * treated as proof of presence.
 */
class AttendanceService
{
    public function today(User $u): ?Attendance
    {
        return Attendance::where('user_id', $u->id)->whereDate('date', BusinessDay::today())->first();
    }

    /** @throws AttendanceConflict when already checked in today */
    public function checkIn(User $u, array $d): Attendance
    {
        $dist = $u->distributor;
        if ($existing = $this->today($u)) {
            throw new AttendanceConflict('You have already checked in today.', $existing);
        }
        [$distance, $within] = $this->evaluate($dist, $d);
        try {
            $a = Attendance::create([
                'user_id' => $u->id, 'distributor_id' => $dist->id, 'date' => BusinessDay::today(), 'status' => 'checked_in',
            ] + $this->fields('check_in', $d, $distance, $within));
        } catch (UniqueConstraintViolationException) {
            throw new AttendanceConflict('You have already checked in today.', $this->today($u));
        }
        AuditLog::record('attendance.check_in', $a, ['within_geofence' => $within]);

        return $a;
    }

    /** @throws AttendanceConflict when already checked out today */
    public function checkOut(User $u, array $d): Attendance
    {
        return DB::transaction(function () use ($u, $d) {
            $a = Attendance::where('user_id', $u->id)->whereDate('date', BusinessDay::today())->lockForUpdate()->first();
            if (! $a) {
                throw new RuntimeException('You have not checked in today.');
            }
            if ($a->status === 'checked_out') {
                throw new AttendanceConflict('You have already checked out today.', $a);
            }
            [$distance, $within] = $this->evaluate($u->distributor, $d);
            $a->update(['status' => 'checked_out', 'remarks' => $d['remarks'] ?? $a->remarks]
                + $this->fields('check_out', $d, $distance, $within));
            AuditLog::record('attendance.check_out', $a, ['within_geofence' => $within]);

            return $a;
        });
    }

    /** Orders/collections may require an open check-in (config fieldapp.attendance.require_check_in). */
    public function assertOnDuty(User $u): void
    {
        if (config('fieldapp.attendance.require_check_in') && $this->today($u)?->status !== 'checked_in') {
            throw new RuntimeException('Check in before recording orders or collections.');
        }
    }

    public function geofence(Distributor $d): array
    {
        $mode = config('fieldapp.attendance.geofence_mode');

        return $d->hasGeofence() && $mode !== 'off'
            ? ['configured' => true, 'mode' => $mode, 'latitude' => $d->latitude, 'longitude' => $d->longitude, 'radius_m' => $d->geofence_radius_m]
            : ['configured' => false, 'mode' => $mode];
    }

    /** @return array{0: ?int, 1: ?bool} distance in metres and inside/outside, null when no geofence */
    private function evaluate(Distributor $dist, array $d): array
    {
        $fence = $this->geofence($dist);
        if (! $fence['configured']) {
            return [null, null];
        }
        $distance = (int) round(self::distanceM($fence['latitude'], $fence['longitude'], (float) $d['latitude'], (float) $d['longitude']));
        $within = $distance <= $fence['radius_m'];
        if ($fence['mode'] === 'enforce') {
            $maxAcc = config('fieldapp.attendance.max_accuracy_m');
            if ($maxAcc !== null && (! isset($d['accuracy_m']) || $d['accuracy_m'] > $maxAcc)) {
                throw new RuntimeException("Location accuracy must be within {$maxAcc} m. Move to open sky and retry.");
            }
            if (! $within) {
                throw new RuntimeException("You are {$distance} m from the permitted area (limit {$fence['radius_m']} m).");
            }
        }

        return [$distance, $within];
    }

    private function fields(string $p, array $d, ?int $distance, ?bool $within): array
    {
        return [
            $p.'_at' => now(), $p.'_client_at' => $d['client_timestamp'] ?? null,
            $p.'_lat' => $d['latitude'], $p.'_lng' => $d['longitude'], $p.'_accuracy_m' => $d['accuracy_m'] ?? null,
            $p.'_distance_m' => $distance, $p.'_within_geofence' => $within,
        ];
    }

    /** Great-circle distance (haversine), metres. */
    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
