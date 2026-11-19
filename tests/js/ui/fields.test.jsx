import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import NumberField from '../../../resources/js/components/ui/NumberField';
import Select from '../../../resources/js/components/ui/Select';
import TextField from '../../../resources/js/components/ui/TextField';

test('ties the label to the input', () => {
    render(<TextField id="order_number" label="Order number" />);

    expect(screen.getByLabelText('Order number')).toHaveAttribute('id', 'order_number');
});

test('reads out the hint', () => {
    render(<TextField id="plate" label="License plate" hint="Letters and numbers only" />);

    expect(screen.getByLabelText('License plate')).toHaveAttribute('aria-describedby', 'plate-hint');
    expect(screen.getByText('Letters and numbers only')).toBeInTheDocument();
});

test('shows a field error from the server and marks the input', () => {
    render(<TextField id="plate" label="License plate" error="The license plate field is required." />);

    const input = screen.getByLabelText('License plate');

    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('aria-describedby', 'plate-error');
    expect(screen.getByText('The license plate field is required.')).toBeInTheDocument();
});

test('reads out hint and error together', () => {
    render(<TextField id="plate" label="Plate" hint="Letters and numbers" error="Required" />);

    expect(screen.getByLabelText('Plate')).toHaveAttribute('aria-describedby', 'plate-hint plate-error');
});

test('says nothing about a field that is fine', () => {
    render(<TextField id="plate" label="Plate" />);

    const input = screen.getByLabelText('Plate');

    expect(input).not.toHaveAttribute('aria-describedby');
    expect(input).not.toHaveAttribute('aria-invalid');
});

test('passes what the user types on', () => {
    const onChange = vi.fn();

    render(<TextField id="plate" label="Plate" value="" onChange={onChange} />);

    fireEvent.change(screen.getByLabelText('Plate'), { target: { value: 'HHAB1234' } });

    expect(onChange).toHaveBeenCalled();
});

test('counts pallets as a number', () => {
    render(<NumberField id="pallets" label="Pallets" min="1" max="70" />);

    const input = screen.getByLabelText('Pallets');

    expect(input).toHaveAttribute('type', 'number');
    expect(input).toHaveAttribute('max', '70');
});

test('offers the choices of a select', () => {
    render(
        <Select id="supplier_id" label="Supplier" error="This supplier is not approved.">
            <option value="">Please choose</option>
            <option value="4">Nordfracht GmbH</option>
        </Select>
    );

    expect(screen.getByLabelText('Supplier')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('option', { name: 'Nordfracht GmbH' })).toBeInTheDocument();
});
