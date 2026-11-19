export const statuses = ['booked', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show'];

export const types = ['inbound', 'outbound'];

const labels = {
    booked: 'Booked',
    arrived: 'Arrived',
    in_progress: 'In progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: 'No show',
};

const blocks = {
    booked: {
        inbound: 'bg-brand-50 border border-brand-500 text-brand-800',
        outbound: 'bg-outbound-50 border border-outbound-500 text-outbound-700',
    },
    arrived: {
        inbound: 'bg-white border-2 border-dashed border-brand-700 text-brand-800',
        outbound: 'bg-white border-2 border-dashed border-outbound-700 text-outbound-700',
    },
    in_progress: {
        inbound: 'bg-brand-700 border border-brand-700 text-white',
        outbound: 'bg-outbound-700 border border-outbound-700 text-white',
    },
    completed: {
        inbound: 'bg-line border border-field text-muted',
        outbound: 'bg-line border border-field text-muted',
    },
    cancelled: {
        inbound: 'bg-white border border-dashed border-field text-muted line-through',
        outbound: 'bg-white border border-dashed border-field text-muted line-through',
    },
    no_show: {
        inbound: 'bg-danger-50 border border-danger-700 text-danger-700',
        outbound: 'bg-danger-50 border border-danger-700 text-danger-700',
    },
};

const chips = {
    booked: 'bg-brand-50 text-brand-800',
    arrived: 'bg-white text-brand-800 ring-1 ring-brand-700',
    in_progress: 'bg-brand-700 text-white',
    completed: 'bg-line text-muted',
    cancelled: 'bg-line text-muted',
    no_show: 'bg-danger-50 text-danger-700',
};

export function getStatusStyle(status, type = 'inbound') {
    const kind = types.includes(type) ? type : 'inbound';

    return {
        label: labels[status] ?? status,
        block: blocks[status]?.[kind] ?? blocks.booked[kind],
        chip: chips[status] ?? chips.booked,
    };
}

export function getStatusLabel(status) {
    return labels[status] ?? status;
}

export const plateMismatchStyle = {
    label: 'Check plate',
    chip: 'bg-warn-50 text-warn-700',
};

export const dockOccupiedStyle = {
    label: 'Dock occupied',
    chip: 'bg-warn-50 text-warn-700',
};

export function getDockColumnStyle(isActive) {
    return isActive
        ? { column: 'bg-white', header: 'text-ink', label: null }
        : {
              column: 'bg-[image:repeating-linear-gradient(45deg,var(--color-line)_0,var(--color-line)_2px,transparent_2px,transparent_8px)]',
              header: 'text-muted',
              label: 'Inactive',
          };
}
