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

    /*
    |--------------------------------------------------------------------------
    | Changing a booking
    |--------------------------------------------------------------------------
    |
    | How close to a slot a supplier may still move or cancel their booking.
    | An admin is not bound by this.
    |
    */

    'change_deadline_hours' => 3,

    /*
    |--------------------------------------------------------------------------
    | Live status
    |--------------------------------------------------------------------------
    |
    | How long before a slot a supplier is shown whether the dock is free, and
    | how often the page asks again while that window is open.
    |
    */

    'status_window_minutes' => 15,

    'status_poll_seconds' => 30,

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | How many writing requests one account may send per minute. Suppliers and
    | admins are kept low, because a slot that is taken is taken; the warehouse
    | works through many lorries a day and needs more room.
    |
    */

    'writes_per_minute' => 20,

    'warehouse_actions_per_minute' => 120,

    'days_per_page' => 5,

    'bookings_per_page' => 20,

];
