<?php

namespace App\Services;

use App\Models\Attendance;
use RuntimeException;

/** Duplicate check-in / check-out; carries the existing record so the client can resync. */
class AttendanceConflict extends RuntimeException
{
    public function __construct(string $message, public readonly ?Attendance $attendance)
    {
        parent::__construct($message);
    }
}
