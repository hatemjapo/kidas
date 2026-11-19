<?php

use App\Enums\BookingStatus;

test('the frontend knows every status the backend can send', function () {
    $source = file_get_contents(base_path('resources/js/lib/statusStyles.js'));

    expect($source)->toBeString();

    preg_match('/export const statuses = \[(.*?)\];/s', (string) $source, $matches);

    $inTheApp = array_map(
        fn (string $status): string => trim($status, " \n'\""),
        explode(',', trim($matches[1] ?? '', " \n")),
    );

    $inTheBackend = array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::cases());

    expect($inTheApp)->toBe($inTheBackend);
});

test('the frontend spells the statuses the way the backend labels them', function () {
    $source = (string) file_get_contents(base_path('resources/js/lib/statusStyles.js'));

    foreach (BookingStatus::cases() as $status) {
        expect($source)->toContain("{$status->value}: '{$status->label()}'");
    }
});
