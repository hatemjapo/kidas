<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pallets
    |--------------------------------------------------------------------------
    |
    | The highest number of pallets a single booking may carry. The pallet
    | rules cover every count from one up to this number.
    |
    */

    'max_pallets' => 70,

    /*
    |--------------------------------------------------------------------------
    | Opening times
    |--------------------------------------------------------------------------
    |
    | Opening and shift times are wall clock times of the warehouse, while the
    | application stores instants in UTC. This is the zone they are read in.
    |
    */

    'timezone' => 'Europe/Berlin',

    /*
    |--------------------------------------------------------------------------
    | Booking a slot
    |--------------------------------------------------------------------------
    |
    | How far ahead of a slot a booking is still accepted, and how many days
    | the slot suggestion hands out at a time.
    |
    */

    'lead_time_hours' => 3,

    'days_per_page' => 5,

    'bookings_per_page' => 20,

];
