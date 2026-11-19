import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import IconButton from '../../../resources/js/components/ui/IconButton';
import { CloseIcon } from '../../../resources/js/components/ui/icons';

test('has an accessible name and a touch target of 44 pixels', () => {
    render(
        <IconButton label="Close">
            <CloseIcon />
        </IconButton>
    );

    const button = screen.getByRole('button', { name: 'Close' });

    expect(button).toHaveClass('size-11');
    expect(button).toHaveAttribute('title', 'Close');
});

test('calls back when it is pressed', () => {
    const onClick = vi.fn();

    render(
        <IconButton label="Close" onClick={onClick}>
            <CloseIcon />
        </IconButton>
    );

    fireEvent.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClick).toHaveBeenCalled();
});

test('refuses to be built without a name', () => {
    expect(() => render(<IconButton><CloseIcon /></IconButton>)).toThrow(/needs a label/);
});

test('hides its icon from the screen reader', () => {
    const { container } = render(
        <IconButton label="Close">
            <CloseIcon />
        </IconButton>
    );

    expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
});
