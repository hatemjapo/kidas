import { useEffect } from 'react';

export default function Modal({ onClose, children }) {
    useEffect(() => {
        function closeOnEscape(event) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [onClose]);

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 sm:p-10"
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                role="dialog"
                aria-modal="true"
                className="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl"
            >
                {children}
            </div>
        </div>
    );
}
