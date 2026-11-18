<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\DurationForPallets;
use App\Actions\Bookings\FindBookableDays;
use App\Enums\DockUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlotQueryRequest;
use App\Http\Resources\BookableDayResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SlotController extends Controller
{
    public function index(
        SlotQueryRequest $request,
        FindBookableDays $findBookableDays,
        DurationForPallets $durationForPallets,
    ): AnonymousResourceCollection {
        $usage = DockUsage::from($request->string('type')->toString());
        $pallets = $request->integer('pallets');

        $before = $request->date('before');

        $days = $before === null
            ? $findBookableDays->getNextBookableDays($usage, $pallets, $request->date('after'))
            : $findBookableDays->getPreviousBookableDays($usage, $pallets, $before);

        return BookableDayResource::collection($days)->additional([
            'meta' => [
                'duration_minutes' => $durationForPallets->getMinutesForPallets($pallets),
            ],
        ]);
    }
}
