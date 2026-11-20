import Field, { fieldClasses, fieldDescription } from './Field';

export default function Select({ id, label, hint, error, children, className = '', ...rest }) {
    return (
        <Field id={id} label={label} hint={hint} error={error}>
            <select
                id={id}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={fieldDescription(id, hint, error)}
                className={`${fieldClasses} ${className}`}
                {...rest}
            >
                {children}
            </select>
        </Field>
    );
}
