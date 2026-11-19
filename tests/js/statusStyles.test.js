import { describe, expect, test } from 'vitest';
import {
    dockOccupiedStyle,
    getDockColumnStyle,
    getStatusLabel,
    getStatusStyle,
    plateMismatchStyle,
    statuses,
    types,
} from '../../resources/js/lib/statusStyles';

test('knows every status the application can be in', () => {
    expect(statuses).toEqual(['booked', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show']);
});

describe.each(statuses)('%s', (status) => {
    test.each(types)('has a label, a block and a chip for %s', (type) => {
        const style = getStatusStyle(status, type);

        expect(style.label).toMatch(/^[A-Z]/);
        expect(style.block).not.toBe('');
        expect(style.chip).not.toBe('');
    });
});

test('names every status in English words, never by colour alone', () => {
    expect(statuses.map(getStatusLabel)).toEqual([
        'Booked',
        'Arrived',
        'In progress',
        'Completed',
        'Cancelled',
        'No show',
    ]);
});

test('tells a delivery apart from a pickup while it is awaited or handled', () => {
    expect(getStatusStyle('booked', 'inbound').block).not.toBe(getStatusStyle('booked', 'outbound').block);
    expect(getStatusStyle('in_progress', 'inbound').block).not.toBe(getStatusStyle('in_progress', 'outbound').block);
});

test('shows a finished lorry the same way whatever it carried', () => {
    expect(getStatusStyle('completed', 'inbound').block).toBe(getStatusStyle('completed', 'outbound').block);
    expect(getStatusStyle('no_show', 'inbound').block).toBe(getStatusStyle('no_show', 'outbound').block);
});

test('falls back to a delivery when the type is unknown', () => {
    expect(getStatusStyle('booked', 'sideways').block).toBe(getStatusStyle('booked', 'inbound').block);
    expect(getStatusStyle('booked').block).toBe(getStatusStyle('booked', 'inbound').block);
});

test('says the status back when it does not know it', () => {
    expect(getStatusStyle('rescheduled').label).toBe('rescheduled');
    expect(getStatusLabel('rescheduled')).toBe('rescheduled');
});

test('uses only token classes, never a hex value', () => {
    const everything = statuses
        .flatMap((status) => types.map((type) => getStatusStyle(status, type)))
        .flatMap((style) => [style.block, style.chip])
        .concat([plateMismatchStyle.chip, dockOccupiedStyle.chip]);

    everything.forEach((classes) => expect(classes).not.toMatch(/#[0-9a-f]{3,6}/i));
});

test('warns in words about a plate that differs and a dock that is busy', () => {
    expect(plateMismatchStyle.label).toBe('Check plate');
    expect(plateMismatchStyle.chip).toContain('warn');
    expect(dockOccupiedStyle.label).toBe('Dock occupied');
});

test('marks a dock that is switched off', () => {
    expect(getDockColumnStyle(true).label).toBeNull();
    expect(getDockColumnStyle(false).label).toBe('Inactive');
    expect(getDockColumnStyle(false).header).toContain('muted');
});
