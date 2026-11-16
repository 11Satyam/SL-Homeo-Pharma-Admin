<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns device coordinates into a short place name ("MG Road, Hazratganj, Lucknow, Uttar Pradesh 226001")
 * using OpenStreetMap Nominatim (free, no API key). Results are cached by ~11 m cells and saved on the
 * attendance row, so each place is looked up once. A failed lookup returns null and the UI shows coordinates.
 */
class Geocoder
{
    public function enabled(): bool
    {
        return (bool) config('fieldapp.attendance.geocode');
    }

    public function reverse(float $lat, float $lng): ?string
    {
        if (! $this->enabled() || ($lat == 0 && $lng == 0)) {
            return null;
        }
        $key = 'geo:'.round($lat, 4).','.round($lng, 4);
        if (($hit = Cache::get($key)) !== null) {
            return $hit === '' ? null : $hit;
        }
        try {
            $res = Http::timeout(5)->withHeaders(['User-Agent' => 'SLPharma-ERP/1.0 (attendance)', 'Accept-Language' => 'en'])
                ->get(config('fieldapp.attendance.geocode_url'), ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 18, 'addressdetails' => 1]);
            $label = $res->successful() ? self::label((array) $res->json()) : null;
        } catch (Throwable $e) {
            Log::warning('Reverse geocoding failed: '.$e->getMessage());
            $label = null;
        }
        // Remember a miss briefly so a bad spot or an outage is not retried on every page load.
        $label ? Cache::forever($key, $label) : Cache::put($key, '', now()->addMinutes(30));

        return $label;
    }

    /** Short, readable address from a Nominatim response: street, area, city, state + PIN (no country). */
    public static function label(array $r): ?string
    {
        $a = $r['address'] ?? [];
        $street = trim(implode(' ', array_filter([$a['house_number'] ?? null, $a['road'] ?? $a['pedestrian'] ?? null])));
        $parts = array_filter([
            $a['amenity'] ?? $a['shop'] ?? $a['building'] ?? null,
            $street ?: null,
            $a['neighbourhood'] ?? $a['quarter'] ?? $a['suburb'] ?? null,
            $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['county'] ?? $a['state_district'] ?? null,
            trim(($a['state'] ?? '').' '.($a['postcode'] ?? '')) ?: null,
        ]);
        $parts = array_values(array_unique($parts));
        if (! $parts && ! empty($r['display_name'])) {
            $parts = array_slice(explode(', ', preg_replace('/, India$/', '', $r['display_name'])), 0, 5);
        }

        return $parts ? mb_substr(implode(', ', $parts), 0, 255) : null;
    }

    /** Fill missing check-in/out addresses on one attendance row. Returns how many were looked up. */
    public function fill(Attendance $a): int
    {
        $n = 0;
        foreach (['check_in', 'check_out'] as $p) {
            // The key check keeps this harmless if the address columns are not migrated yet.
            if ($a->{$p.'_lat'} !== null && array_key_exists($p.'_address', $a->getAttributes()) && $a->{$p.'_address'} === null) {
                $n++;
                if ($label = $this->reverse((float) $a->{$p.'_lat'}, (float) $a->{$p.'_lng'})) {
                    $a->forceFill([$p.'_address' => $label])->saveQuietly();
                }
            }
        }

        return $n;
    }
}
