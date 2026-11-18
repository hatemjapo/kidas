<?php

namespace App\Http\Controllers\Api;

use App\Actions\Schedule\GetDaySchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ScheduleQueryRequest;
use App\Http\Resources\ScheduleResource;
use Carbon\CarbonImmutable;

class ScheduleController extends Controller
{
    public function show(ScheduleQueryRequest $request, GetDaySchedule $getDaySchedule): ScheduleResource
    {
        return new ScheduleResource(
            $getDaySchedule->getScheduleForDate(
                CarbonImmutable::parse($request->string('date')->toString())
            )
        );
    }
}
