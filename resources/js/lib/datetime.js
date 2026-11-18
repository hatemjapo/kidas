const timeZone = 'Europe/Berlin';

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const timeFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
});

export function formatDateTime(iso) {
    return dateTimeFormatter.format(new Date(iso));
}

export function formatDate(iso) {
    return dateFormatter.format(new Date(iso));
}

export function formatTime(iso) {
    return timeFormatter.format(new Date(iso));
}
