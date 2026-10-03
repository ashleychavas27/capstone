<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clinic schedule
    |--------------------------------------------------------------------------
    |
    | Opening hours per ISO weekday (1 = Monday … 7 = Sunday); null = closed.
    | The client's schedule is Monday to Saturday, 9:00 AM – 4:00 PM, closed on
    | Sundays. Holidays are open, so no holiday calendar is needed.
    |
    */

    'hours' => [
        1 => ['09:00', '16:00'],
        2 => ['09:00', '16:00'],
        3 => ['09:00', '16:00'],
        4 => ['09:00', '16:00'],
        5 => ['09:00', '16:00'],
        6 => ['09:00', '16:00'],
        7 => null,
    ],

    /*
    | Lunch break. No appointment may overlap this window; the clinic is open
    | either side of it.
    */
    'lunch' => ['12:00', '13:00'],

    /*
    | Slots are offered on this granularity, and an appointment must finish
    | inside opening hours.
    */
    'slot_minutes' => 30,

    /*
    | How far ahead patients may book, in months.
    */
    'window_months' => 2,

    /*
    | Used when an appointment's procedure is unknown (historical rows entered
    | before durations existed, or free-text services typed by staff).
    */
    'default_duration' => 30,

    /*
    |--------------------------------------------------------------------------
    | Dental procedures and estimated duration
    |--------------------------------------------------------------------------
    |
    | minutes = null means "by appointment": the clinic books these itself and
    | they are not offered for online self-booking (`online` => false), because
    | they need a longer or multi-visit arrangement.
    |
    | A procedure that says "1 to 1.5 hours" reserves the longer time so the
    | dentist cannot be double-booked into a session that overruns.
    |
    */

    'procedures' => [
        [
            'name' => 'Dental Cleaning',
            'local' => 'Cleaning',
            'display' => '1 – 1.5 hours',
            'minutes' => 90,
            'online' => true,
        ],
        [
            'name' => 'Dental X-ray',
            'local' => 'Binilog',
            'display' => '30 minutes',
            'minutes' => 30,
            'online' => true,
        ],
        [
            'name' => 'Tooth Extraction',
            'local' => 'Gabot',
            'display' => '1 hour',
            'minutes' => 60,
            'online' => true,
        ],
        [
            'name' => 'Dental Filling',
            'local' => 'Pasta',
            'display' => '1 hour',
            'minutes' => 60,
            'online' => true,
        ],
        [
            'name' => 'Braces Adjustment',
            'local' => 'Ortho / Braces',
            'display' => '30 minutes',
            'minutes' => 30,
            'online' => true,
        ],
        [
            'name' => 'Dental Surgery',
            'local' => 'Surgery',
            'display' => 'By appointment',
            'minutes' => null,
            'online' => false,
        ],
        [
            'name' => 'Braces Installation',
            'local' => 'Takod ka Braces',
            'display' => 'By appointment',
            'minutes' => null,
            'online' => false,
        ],
        [
            'name' => 'Root Canal Treatment',
            'local' => 'Root Canal',
            'display' => 'By appointment',
            'minutes' => null,
            'online' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Services the clinic also schedules
    |--------------------------------------------------------------------------
    |
    | The client's duration sheet does not cover these, but they were already
    | bookable before it arrived (and existing records use these names), so they
    | stay on the menu. `minutes` is null because the client gave no estimate —
    | `clinic.default_duration` is used until the clinic supplies one, and the
    | procedure table shows "Duration not specified" for them.
    |
    */

    'services' => [
        [
            'name' => 'General Checkup',
            'local' => 'Checkup',
            'display' => 'Duration not specified',
            'minutes' => null,
            'online' => true,
        ],
        [
            'name' => 'Dental Crown',
            'local' => 'Crown',
            'display' => 'Duration not specified',
            'minutes' => null,
            'online' => true,
        ],
        [
            'name' => 'Braces / Orthodontic Consultation',
            'local' => 'Consultation',
            'display' => 'Duration not specified',
            'minutes' => null,
            'online' => true,
        ],
        [
            'name' => 'Teeth Whitening',
            'local' => 'Whitening',
            'display' => 'Duration not specified',
            'minutes' => null,
            'online' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dentist duty schedule
    |--------------------------------------------------------------------------
    |
    | Duty days live on each dentist account (users.duty_days, e.g. "1,2,3,5")
    | so the clinic can change them from the Accounts screen without a code
    | change, and so a fresh database seeds the same schedule the app enforces:
    | Dr. Jana Gay Gil — Mon, Tue, Wed, Fri; Dr. Wenca Louise Dajay Ortizo —
    | Thu, Sat. A dentist with no days recorded falls back to the default below,
    | so adding staff never blocks them.
    |
    */

    'default_duty_days' => [1, 2, 3, 4, 5, 6],

];
