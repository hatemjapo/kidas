import Field, { fieldClasses, fieldDescription } from './Field';

export default function NumberField({ id, label, hint, error, className = 'w-28', ...rest }) {
    return (
        <Field id={id} label={label} hint={hint} error={error}>
            <input
                id={id}
                type="number"
                inputMode="numeric"
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={fieldDescription(id, hint, error)}
                className={`${fieldClasses} ${className}`}
                {...rest}
            />
        </Field>
    );
}
