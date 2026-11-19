import { render } from '@testing-library/react';
import { vi } from 'vitest';
import Alert from '../../../resources/js/components/ui/Alert';
import Button from '../../../resources/js/components/ui/Button';
import Card from '../../../resources/js/components/ui/Card';
import ChoiceTile from '../../../resources/js/components/ui/ChoiceTile';
import Dialog from '../../../resources/js/components/ui/Dialog';
import IconButton from '../../../resources/js/components/ui/IconButton';
import InfoBox from '../../../resources/js/components/ui/InfoBox';
import NumberField from '../../../resources/js/components/ui/NumberField';
import SegmentedControl from '../../../resources/js/components/ui/SegmentedControl';
import Select from '../../../resources/js/components/ui/Select';
import StatTile from '../../../resources/js/components/ui/StatTile';
import StatusChip from '../../../resources/js/components/ui/StatusChip';
import TextField from '../../../resources/js/components/ui/TextField';

const pieces = [
    ['Button', <Button>Book</Button>],
    ['IconButton', <IconButton label="Close">x</IconButton>],
    ['TextField', <TextField id="a" label="A" hint="h" error="e" />],
    ['NumberField', <NumberField id="b" label="B" />],
    ['Select', <Select id="c" label="C" />],
    ['Card', <Card title="D">d</Card>],
    ['StatTile', <StatTile label="E" value={1} />],
    ['StatusChip', <StatusChip status="booked" />],
    ['SegmentedControl', <SegmentedControl name="f" label="F" options={[{ value: 'g', label: 'G' }]} value="g" onChange={vi.fn()} />],
    ['ChoiceTile', <ChoiceTile>H</ChoiceTile>],
    ['InfoBox', <InfoBox>I</InfoBox>],
    ['Alert', <Alert>J</Alert>],
    ['Dialog', <Dialog title="K" onClose={vi.fn()}>k</Dialog>],
];

test.each(pieces)('%s dresses itself from the tokens, never from a hex value', (name, element) => {
    const { container } = render(element);

    expect(container.innerHTML).not.toMatch(/#[0-9a-f]{3,8}\b/i);
    expect(container.innerHTML).not.toMatch(/\b(bg|text|border)-(slate|gray|zinc|neutral|stone|emerald|green|amber|red|blue|sky)-\d/);
});
