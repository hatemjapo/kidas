import { navigationFor } from '../../resources/js/navigation';

test('gives an admin every area', () => {
    expect(navigationFor('admin').map((item) => item.label))
        .toEqual(['Dashboard', 'Dock schedule', 'Bookings']);
});

test('gives warehouse staff the dock schedule', () => {
    expect(navigationFor('staff').map((item) => item.label))
        .toEqual(['Dashboard', 'Dock schedule']);
});

test('gives a supplier only their own bookings', () => {
    expect(navigationFor('supplier').map((item) => item.label))
        .toEqual(['Dashboard', 'My bookings']);
});

test('gives an unknown role nothing', () => {
    expect(navigationFor('manager')).toEqual([]);
});

test('gives a guest nothing', () => {
    expect(navigationFor(null)).toEqual([]);
    expect(navigationFor(undefined)).toEqual([]);
});
