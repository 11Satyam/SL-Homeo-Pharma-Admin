<?php

namespace App\Http\Resources\App;

use App\Support\BusinessDay;
use Illuminate\Http\Resources\Json\JsonResource;

/** Field-app response conventions: money as 2dp strings, times as ISO-8601 in the business timezone. */
abstract class ApiResource extends JsonResource
{
    protected static function money($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    protected static function iso($dt): ?string
    {
        return BusinessDay::iso($dt);
    }
}
