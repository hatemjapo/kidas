import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import ChoiceTile from '../../../resources/js/components/ui/ChoiceTile';
import SegmentedControl from '../../../resources/js/components/ui/SegmentedControl';

const options = [
    { value: 'inbound', label: 'Deliver' },
    { value: 'outbound', label: 'Pick up' },
];

test('a segmented control says which side is chosen', () => {
    render(<SegmentedControl name="type" label="I want to" options={options} value="inbound" onChange={vi.fn()} />);

    expect(screen.getByRole('button', { name: 'Deliver' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Pick up' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('group', { name: 'I want to' })).toBeInTheDocument();
});

test('a segmented control hands the other side over', () => {
    const onChange = vi.fn();

    render(<SegmentedControl name="type" label="I want to" options={options} value="inbound" onChange={onChange} />);

    fireEvent.click(screen.getByRole('button', { name: 'Pick up' }));

    expect(onChange).toHaveBeenCalledWith('outbound');
});

test('a choice tile says whether it is chosen', () => {
    render(<ChoiceTile chosen>06:00</ChoiceTile>);

    const tile = screen.getByRole('button', { name: '06:00' });

    expect(tile).toHaveAttribute('aria-pressed', 'true');
    expect(tile).toHaveClass('bg-brand-50');
});

test('a choice tile keeps a touch target of 44 pixels', () => {
    render(<ChoiceTile>06:15</ChoiceTile>);

    expect(screen.getByRole('button')).toHaveClass('min-h-11');
});

test('a choice tile that is taken cannot be picked', () => {
    const onClick = vi.fn();

    render(
        <ChoiceTile disabled onClick={onClick}>
            06:30
        </ChoiceTile>
    );

    fireEvent.click(screen.getByRole('button'));

    expect(onClick).not.toHaveBeenCalled();
});
