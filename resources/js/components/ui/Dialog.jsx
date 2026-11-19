import { useEffect, useRef } from 'react';
import Button from './Button';
import IconButton from './IconButton';
import { CloseIcon } from './icons';

const focusable = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

export default function Dialog({ title, onClose, children, footer, className = '' }) {
    const panel = useRef(null);
    const openedFrom = useRef(null);

    useEffect(() => {
        openedFrom.current = document.activeElement;

        const first = panel.current?.querySelector(focusable);

        if (first instanceof HTMLElement) {
            first.focus();
        } else {
            panel.current?.focus();
        }

        function keepTheFocusInside(event) {
            if (event.key === 'Escape') {
                onClose();

                return;
            }

            if (event.key !== 'Tab' || panel.current === null) {
                return;
            }

            const inside = [...panel.current.querySelectorAll(focusable)].filter(
                (element) => ! element.hasAttribute('hidden') && element.getAttribute('aria-hidden') !== 'true'
            );

            if (inside.length === 0) {
                return;
            }

            const edge = event.shiftKey ? inside[0] : inside[inside.length - 1];

            if (document.activeElement === edge) {
                event.preventDefault();
                (event.shiftKey ? inside[inside.length - 1] : inside[0]).focus();
            }
        }

        document.addEventListener('keydown', keepTheFocusInside);

        return () => {
            document.removeEventListener('keydown', keepTheFocusInside);

            if (openedFrom.current instanceof HTMLElement) {
                openedFrom.current.focus();
            }
        };
    }, [onClose]);

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 sm:p-10"
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                ref={panel}
                role="dialog"
                aria-modal="true"
                aria-label={title}
                tabIndex={-1}
                className={`w-full max-w-2xl rounded-card border border-line bg-white p-5 ${className}`}
            >
                <header className="mb-4 flex items-start justify-between gap-4">
                    <h2 className="text-lg font-bold text-ink">{title}</h2>

                    <IconButton label="Close" onClick={onClose}>
                        <CloseIcon />
                    </IconButton>
                </header>

                {children}

                {footer && <div className="mt-5 flex flex-wrap items-center gap-3">{footer}</div>}
            </div>
        </div>
    );
}

export function ConfirmDialog({
    title,
    question,
    confirmLabel = 'Yes, do it',
    cancelLabel = 'Keep it',
    tone = 'danger',
    working = false,
    onConfirm,
    onClose,
}) {
    return (
        <Dialog
            title={title}
            onClose={onClose}
            footer={
                <>
                    <Button variant={tone === 'danger' ? 'danger' : 'primary'} loading={working} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>

                    <Button variant="secondary" onClick={onClose}>
                        {cancelLabel}
                    </Button>
                </>
            }
        >
            <p className="text-[15px] text-ink">{question}</p>
        </Dialog>
    );
}
