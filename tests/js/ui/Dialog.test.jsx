import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Button from '../../../resources/js/components/ui/Button';
import Dialog, { ConfirmDialog } from '../../../resources/js/components/ui/Dialog';

test('names itself and offers a way out', () => {
    render(
        <Dialog title="Booking 7" onClose={vi.fn()}>
            <p>Nordfracht GmbH</p>
        </Dialog>
    );

    const dialog = screen.getByRole('dialog', { name: 'Booking 7' });

    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument();
});

test('puts the focus inside when it opens', () => {
    render(
        <Dialog title="Booking 7" onClose={vi.fn()}>
            <button type="button">Register the arrival</button>
        </Dialog>
    );

    expect(screen.getByRole('button', { name: 'Close' })).toHaveFocus();
});

test('keeps the focus from leaving at the end', () => {
    render(
        <Dialog title="Booking 7" onClose={vi.fn()} footer={<Button>Save</Button>}>
            <button type="button">Register the arrival</button>
        </Dialog>
    );

    const save = screen.getByRole('button', { name: 'Save' });

    save.focus();
    fireEvent.keyDown(document, { key: 'Tab' });

    expect(screen.getByRole('button', { name: 'Close' })).toHaveFocus();
});

test('wraps backwards at the start as well', () => {
    render(
        <Dialog title="Booking 7" onClose={vi.fn()} footer={<Button>Save</Button>}>
            <button type="button">Register the arrival</button>
        </Dialog>
    );

    fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });

    expect(screen.getByRole('button', { name: 'Save' })).toHaveFocus();
});

test('closes on escape, on the backdrop and on the close button', () => {
    const onClose = vi.fn();

    const { container } = render(
        <Dialog title="Booking 7" onClose={onClose}>
            <p>Nordfracht GmbH</p>
        </Dialog>
    );

    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.click(container.firstChild);
    fireEvent.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClose).toHaveBeenCalledTimes(3);
});

test('stays open when the panel itself is clicked', () => {
    const onClose = vi.fn();

    render(
        <Dialog title="Booking 7" onClose={onClose}>
            <p>Nordfracht GmbH</p>
        </Dialog>
    );

    fireEvent.click(screen.getByRole('dialog'));

    expect(onClose).not.toHaveBeenCalled();
});

test('gives the focus back to where it came from', () => {
    render(<button type="button">Open</button>);

    const opener = screen.getByRole('button', { name: 'Open' });

    opener.focus();

    const dialog = render(
        <Dialog title="Booking 7" onClose={vi.fn()}>
            <p>Nordfracht GmbH</p>
        </Dialog>
    );

    expect(opener).not.toHaveFocus();

    dialog.unmount();

    expect(opener).toHaveFocus();
});

test('a confirm dialog asks before it acts', () => {
    const onConfirm = vi.fn();
    const onClose = vi.fn();

    render(
        <ConfirmDialog
            title="Release dock 1"
            question="Release dock 1 and finish this booking?"
            confirmLabel="Yes, the dock is free"
            onConfirm={onConfirm}
            onClose={onClose}
        />
    );

    expect(screen.getByText('Release dock 1 and finish this booking?')).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, the dock is free' }));

    expect(onConfirm).toHaveBeenCalled();
});

test('a confirm dialog lets the answer be no', () => {
    const onConfirm = vi.fn();
    const onClose = vi.fn();

    render(<ConfirmDialog title="Release dock 1" question="Sure?" onConfirm={onConfirm} onClose={onClose} />);

    fireEvent.click(screen.getByRole('button', { name: 'Keep it' }));

    expect(onConfirm).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
});

test('a confirm dialog shows that it is working', () => {
    render(<ConfirmDialog title="Release dock 1" question="Sure?" working onConfirm={vi.fn()} onClose={vi.fn()} />);

    expect(screen.getByRole('button', { name: 'Yes, do it' })).toHaveAttribute('aria-busy', 'true');
});
