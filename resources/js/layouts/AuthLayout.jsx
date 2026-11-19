import DockGraphic from '../components/ui/DockGraphic';

const traits = ['10 docks', 'Deliveries & pickups', 'Live status per booking'];

export default function AuthLayout({ title, lead, children, aside }) {
    return (
        <div className="flex min-h-dvh flex-col lg:flex-row">
            <aside className="flex flex-col justify-between gap-10 bg-brand-950 px-6 py-10 text-white lg:w-[52%] lg:px-16 lg:py-14">
                <div>
                    <span className="inline-flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            className="inline-flex size-10 flex-col justify-center gap-1 rounded-tile bg-brand-500 px-2"
                        >
                            <span className="h-0.5 rounded-full bg-white" />
                            <span className="h-0.5 w-3/4 rounded-full bg-white" />
                            <span className="h-0.5 rounded-full bg-white" />
                        </span>
                        <span className="text-2xl font-bold tracking-tight text-white">KiDas</span>
                    </span>

                    <h2 className="mt-10 max-w-md text-3xl font-bold leading-tight lg:mt-20 lg:text-[44px]">
                        Every lorry at the right dock at the right time.
                    </h2>

                    <p className="mt-6 max-w-md text-base text-brand-300 lg:text-lg">
                        Suppliers book their time slot, the warehouse sees the day per dock, and everybody follows the
                        state of a handling live.
                    </p>
                </div>

                <DockGraphic className="hidden h-auto w-full max-w-xl lg:block" />

                <ul className="flex flex-wrap gap-x-8 gap-y-2 text-sm text-brand-300">
                    {traits.map((trait) => (
                        <li key={trait}>{trait}</li>
                    ))}
                </ul>
            </aside>

            <main className="flex flex-1 items-start justify-center px-4 py-10 sm:px-8 lg:items-center lg:py-16">
                <div className="w-full max-w-md">
                    <h1 className="text-3xl font-bold tracking-tight text-ink">{title}</h1>

                    {lead && <p className="mt-2 text-[15px] text-muted">{lead}</p>}

                    {children}

                    {aside}
                </div>
            </main>
        </div>
    );
}
