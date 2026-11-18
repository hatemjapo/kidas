import { navigationFor } from '../../resources/js/navigation';

test('gives an admin every area', () => {
    expect(navigationFor('admin').map((item) => item.label))
        .toEqual(['Dashboard', 'Dock schedule', 'Book for a supplier', 'Bookings']);
});

test('gives warehouse staff the dock schedule', () => {
    expect(navigationFor('staff').map((item) => item.label))
        .toEqual(['Dashboard', 'Dock schedule']);
});

test('gives a supplier booking, their own bookings and their profile', () => {
    expect(navigationFor('supplier').map((item) => item.label))
        .toEqual(['Dashboard', 'Book a slot', 'My bookings', 'Profile']);
});

test('gives nobody but a supplier the profile', () => {
    expect(navigationFor('admin').map((item) => item.to)).not.toContain('/profile');
    expect(navigationFor('staff').map((item) => item.to)).not.toContain('/profile');
});

test('gives an unknown role nothing', () => {
    expect(navigationFor('manager')).toEqual([]);
});

test('gives a guest nothing', () => {
    expect(navigationFor(null)).toEqual([]);
    expect(navigationFor(undefined)).toEqual([]);
});
