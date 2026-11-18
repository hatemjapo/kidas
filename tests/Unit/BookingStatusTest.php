<?php

use App\Enums\BookingStatus;

test('knows which step a booking may take', function (BookingStatus $from, BookingStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [BookingStatus::Booked, BookingStatus::Arrived, true],
    [BookingStatus::Booked, BookingStatus::Cancelled, true],
    [BookingStatus::Booked, BookingStatus::NoShow, true],
    [BookingStatus::Booked, BookingStatus::InProgress, false],
    [BookingStatus::Booked, BookingStatus::Completed, false],
    [BookingStatus::Arrived, BookingStatus::InProgress, true],
    [BookingStatus::Arrived, BookingStatus::Completed, false],
    [BookingStatus::Arrived, BookingStatus::NoShow, false],
    [BookingStatus::InProgress, BookingStatus::Completed, true],
    [BookingStatus::InProgress, BookingStatus::Arrived, false],
    [BookingStatus::Completed, BookingStatus::InProgress, false],
    [BookingStatus::Cancelled, BookingStatus::Booked, false],
    [BookingStatus::NoShow, BookingStatus::Arrived, false],
]);

test('no booking stays where it is', function (BookingStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(BookingStatus::cases());

test('an ended booking goes nowhere', function (BookingStatus $status) {
    expect($status->getNextStates())->toBe([]);
})->with([BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow]);
