const quarter = 15 * 60 * 1000;

export function getQuarterCount(opensAt, closesAt) {
    return Math.max(0, Math.round((new Date(closesAt) - new Date(opensAt)) / quarter));
}

export function getBlockPosition(opensAt, closesAt, startsAt, endsAt) {
    const quarters = getQuarterCount(opensAt, closesAt);
    const from = Math.round((new Date(startsAt) - new Date(opensAt)) / quarter);
    const until = Math.round((new Date(endsAt) - new Date(opensAt)) / quarter);

    const start = Math.max(0, Math.min(from, quarters));
    const end = Math.max(start, Math.min(until, quarters));

    return { start, span: Math.max(1, end - start) };
}

export function getHourMarks(opensAt, closesAt) {
    const marks = [];
    const opens = new Date(opensAt);
    const closes = new Date(closesAt);

    const cursor = new Date(opens);
    cursor.setUTCMinutes(0, 0, 0);

    if (cursor < opens) {
        cursor.setUTCHours(cursor.getUTCHours() + 1);
    }

    while (cursor < closes) {
        marks.push({
            iso: cursor.toISOString(),
            position: getBlockPosition(opensAt, closesAt, cursor.toISOString(), cursor.toISOString()),
        });

        cursor.setUTCHours(cursor.getUTCHours() + 1);
    }

    return marks;
}
