import { getBlockPosition, getHourMarks, getQuarterCount } from '../../resources/js/lib/timeline';

const opensAt = '2027-06-02T04:00:00Z';
const closesAt = '2027-06-02T16:00:00Z';

test('counts the quarter hours of the day', () => {
    expect(getQuarterCount(opensAt, closesAt)).toBe(48);
});

test('counts nothing for a window that is no window', () => {
    expect(getQuarterCount(opensAt, opensAt)).toBe(0);
});

test('places a booking at the start of the day', () => {
    expect(getBlockPosition(opensAt, closesAt, '2027-06-02T04:00:00Z', '2027-06-02T04:45:00Z'))
        .toEqual({ start: 0, span: 3 });
});

test('places a booking in the middle of the day', () => {
    expect(getBlockPosition(opensAt, closesAt, '2027-06-02T07:00:00Z', '2027-06-02T07:15:00Z'))
        .toEqual({ start: 12, span: 1 });
});

test('cuts a booking that starts before the day opens', () => {
    expect(getBlockPosition(opensAt, closesAt, '2027-06-02T03:30:00Z', '2027-06-02T04:30:00Z'))
        .toEqual({ start: 0, span: 2 });
});

test('cuts a booking that runs past the closing time', () => {
    expect(getBlockPosition(opensAt, closesAt, '2027-06-02T15:30:00Z', '2027-06-02T17:00:00Z'))
        .toEqual({ start: 46, span: 2 });
});

test('never places a block of no width', () => {
    expect(getBlockPosition(opensAt, closesAt, '2027-06-02T07:00:00Z', '2027-06-02T07:00:00Z').span).toBe(1);
});

test('marks every full hour inside the window', () => {
    const marks = getHourMarks(opensAt, closesAt);

    expect(marks).toHaveLength(12);
    expect(marks[0].position.start).toBe(0);
    expect(marks[1].position.start).toBe(4);
});
