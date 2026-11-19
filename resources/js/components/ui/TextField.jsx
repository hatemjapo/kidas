import Field, { fieldClasses, fieldDescription } from './Field';

export default function TextField({ id, label, hint, error, className = '', ...rest }) {
    return (
        <Field id={id} label={label} hint={hint} error={error}>
            <input
                id={id}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={fieldDescription(id, hint, error)}
                className={`${fieldClasses} ${className}`}
                {...rest}
            />
        </Field>
    );
}
