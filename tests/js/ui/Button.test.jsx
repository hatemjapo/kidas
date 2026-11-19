import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Button from '../../../resources/js/components/ui/Button';

test('is a primary button on the brand colour by default', () => {
    render(<Button>Book this slot</Button>);

    const button = screen.getByRole('button', { name: 'Book this slot' });

    expect(button).toHaveClass('bg-brand-700');
    expect(button).toHaveClass('h-12');
    expect(button).toHaveAttribute('type', 'button');
});

test.each([
    ['secondary', 'border-field'],
    ['danger', 'text-danger-700'],
])('wears the %s look', (variant, expected) => {
    render(<Button variant={variant}>Leave it</Button>);

    expect(screen.getByRole('button')).toHaveClass(expected);
});

test('comes in the smaller size as well', () => {
    render(<Button size={40}>Today</Button>);

    expect(screen.getByRole('button')).toHaveClass('h-10');
});

test('falls back to the primary look when the variant is unknown', () => {
    render(<Button variant="sparkly">Book</Button>);

    expect(screen.getByRole('button')).toHaveClass('bg-brand-700');
});

test('does nothing while it is disabled', () => {
    const onClick = vi.fn();

    render(
        <Button disabled onClick={onClick}>
            Book
        </Button>
    );

    fireEvent.click(screen.getByRole('button'));

    expect(onClick).not.toHaveBeenCalled();
});

test('says that it is working and refuses a second click', () => {
    const onClick = vi.fn();

    render(
        <Button loading onClick={onClick}>
            Book
        </Button>
    );

    const button = screen.getByRole('button');

    expect(button).toHaveAttribute('aria-busy', 'true');
    expect(button).toBeDisabled();

    fireEvent.click(button);

    expect(onClick).not.toHaveBeenCalled();
});

test('can submit a form when it is asked to', () => {
    render(<Button type="submit">Save</Button>);

    expect(screen.getByRole('button')).toHaveAttribute('type', 'submit');
});
