import { render, screen } from '@testing-library/react';
import Alert from '../../../resources/js/components/ui/Alert';
import Card from '../../../resources/js/components/ui/Card';
import InfoBox from '../../../resources/js/components/ui/InfoBox';
import StatTile from '../../../resources/js/components/ui/StatTile';
import StatusChip, { PlateMismatchChip } from '../../../resources/js/components/ui/StatusChip';
import { statuses } from '../../../resources/js/lib/statusStyles';

test('a card carries a heading and its content', () => {
    render(
        <Card title="Your slot">
            <p>Dock 1</p>
        </Card>
    );

    expect(screen.getByRole('heading', { name: 'Your slot', level: 2 })).toBeInTheDocument();
    expect(screen.getByText('Dock 1')).toBeInTheDocument();
});

test('a card without a heading stays quiet', () => {
    render(
        <Card>
            <p>Dock 1</p>
        </Card>
    );

    expect(screen.queryByRole('heading')).not.toBeInTheDocument();
});

test('a stat tile names what it counts', () => {
    render(<StatTile label="Expected" value={7} />);

    expect(screen.getByText('Expected')).toBeInTheDocument();
    expect(screen.getByText('7')).toHaveAttribute('data-numeric');
});

test.each([
    ['brand', 'bg-brand-50'],
    ['warn', 'bg-warn-50'],
    ['danger', 'bg-danger-50'],
    ['sparkly', 'bg-white'],
])('a %s stat tile is tinted accordingly', (tone, expected) => {
    const { container } = render(<StatTile label="At the dock" value={2} tone={tone} />);

    expect(container.firstChild).toHaveClass(expected);
});

test.each(statuses)('a chip says %s in words', (status) => {
    render(<StatusChip status={status} />);

    expect(screen.getByText(/\w/)).toBeInTheDocument();
});

test('a chip speaks of the status alone, the type is said elsewhere', () => {
    const { container: inbound } = render(<StatusChip status="booked" type="inbound" />);
    const { container: outbound } = render(<StatusChip status="booked" type="outbound" />);

    expect(inbound.firstChild.className).toBe(outbound.firstChild.className);
    expect(inbound.firstChild).toHaveTextContent('Booked');
});

test('a plate that differs is called out in words', () => {
    render(<PlateMismatchChip />);

    expect(screen.getByText('Check plate')).toHaveClass('bg-warn-50');
});

test('an info box explains in the brand tint', () => {
    const { container } = render(<InfoBox>Registrations are approved by the warehouse.</InfoBox>);

    expect(container.firstChild).toHaveClass('bg-brand-50');
    expect(screen.getByText(/approved by the warehouse/)).toBeInTheDocument();
});

test('an alert announces itself', () => {
    render(<Alert>This slot has just been taken.</Alert>);

    const alert = screen.getByRole('alert');

    expect(alert).toHaveTextContent('This slot has just been taken.');
    expect(alert).toHaveClass('bg-danger-50');
});

test('an alert can warn more softly', () => {
    render(<Alert tone="warn">The plate does not match.</Alert>);

    expect(screen.getByRole('alert')).toHaveClass('bg-warn-50');
});
