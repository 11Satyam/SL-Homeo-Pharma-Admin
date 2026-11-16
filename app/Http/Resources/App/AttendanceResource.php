<?php

namespace App\Http\Resources\App;

/** Locations are labelled device_reported: the server cannot verify GPS. */
class AttendanceResource extends ApiResource
{
    public function toArray($request): array
    {
        $side = fn (string $p) => $this->{$p.'_at'} ? [
            'at' => self::iso($this->{$p.'_at'}), 'client_at' => self::iso($this->{$p.'_client_at'}),
            'location' => [
                'latitude' => (float) $this->{$p.'_lat'}, 'longitude' => (float) $this->{$p.'_lng'},
                'accuracy_m' => $this->{$p.'_accuracy_m'} !== null ? (float) $this->{$p.'_accuracy_m'} : null,
                'source' => 'device_reported',
                'address' => $this->{$p.'_address'}, // place name from the map service; null until looked up
            ],
            'distance_m' => $this->{$p.'_distance_m'}, 'within_geofence' => $this->{$p.'_within_geofence'},
        ] : null;

        return [
            'id' => $this->id, 'date' => $this->date->toDateString(), 'status' => $this->status,
            'check_in' => $side('check_in'), 'check_out' => $side('check_out'),
            'worked_minutes' => $this->check_out_at ? (int) $this->check_in_at->diffInMinutes($this->check_out_at) : null,
            'remarks' => $this->remarks,
        ];
    }
}
