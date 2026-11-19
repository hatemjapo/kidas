import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import AuthLayout from '../../resources/js/layouts/AuthLayout';

function renderLayout() {
    const { container } = render(
        <MemoryRouter>
            <AuthLayout title="Sign in to KiDas">
                <p>form</p>
            </AuthLayout>
        </MemoryRouter>
    );

    return container;
}

test('stands the dark panel beside the form on a wide screen', () => {
    const panel = renderLayout().querySelector('aside');

    expect(panel).toHaveClass('bg-brand-950');
    expect(panel).toHaveClass('lg:w-[52%]');
});

test('fills the window from top to bottom', () => {
    expect(renderLayout().firstChild).toHaveClass('min-h-dvh');
});

test('keeps the panel as a strip above the form on a narrow screen', () => {
    const container = renderLayout();
    const panel = container.querySelector('aside');

    expect(container.firstChild).toHaveClass('flex-col');
    expect(container.firstChild).toHaveClass('lg:flex-row');
    expect(panel).not.toHaveClass('hidden');
});

test('shows the dock graphic only where there is room for it', () => {
    const graphic = renderLayout().querySelector('aside svg');

    expect(graphic).toHaveClass('hidden');
    expect(graphic).toHaveClass('lg:block');
    expect(graphic).toHaveAttribute('aria-hidden', 'true');
});

test('carries the wordmark and the page title', () => {
    renderLayout();

    expect(screen.getByText('KiDas')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Sign in to KiDas', level: 1 })).toBeInTheDocument();
});

test('draws itself from the tokens, never from a hex value', () => {
    expect(renderLayout().innerHTML).not.toMatch(/#[0-9a-f]{3,8}\b/i);
});

test('names what the application does in three words each', () => {
    renderLayout();

    ['10 docks', 'Deliveries & pickups', 'Live status per booking'].forEach((trait) => {
        expect(screen.getByText(trait)).toBeInTheDocument();
    });
});

test('carries a lead under the title when there is one', () => {
    render(
        <MemoryRouter>
            <AuthLayout title="Sign in" lead="Sign in with your email address.">
                <p>form</p>
            </AuthLayout>
        </MemoryRouter>
    );

    expect(screen.getByText('Sign in with your email address.')).toBeInTheDocument();
});
