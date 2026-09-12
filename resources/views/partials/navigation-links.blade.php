@foreach ($sections as $section)
    <section class="mb-6 last:mb-0" aria-labelledby="nav-section-{{ $loop->index }}">
        <h2 class="mb-2 px-3 text-xs font-semibold text-[var(--pn-ink-muted)]" id="nav-section-{{ $loop->index }}">{{ $section['label'] }}</h2>
        <ul class="space-y-1">
            @foreach ($section['links'] as [$route, $patterns, $label, $icon])
                @php($active = request()->routeIs(...$patterns))
                <li>
                    <a class="flex min-h-11 items-center gap-3 rounded-[10px] px-3 text-sm font-semibold {{ $active ? 'bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)]' }} focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route($route) }}" @if ($active) aria-current="page" @endif>
                        <svg class="size-5 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            @switch($icon)
                                @case('branches') <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/> @break
                                @case('families') <circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M15 15a4 4 0 0 1 6 3v2"/> @break
                                @case('pricing') <circle cx="12" cy="12" r="8.5"/><path d="M12 7v10M15 9.5c-.7-.7-1.7-1-3-1-1.7 0-3 .8-3 2s1.3 2 3 2 3 .8 3 2-1.3 2-3 2c-1.3 0-2.3-.3-3-1"/> @break
                                @case('tenant') <path d="M4 20h16M6 20V8l6-4 6 4v12M9 11h1M14 11h1M9 15h1M14 15h1"/> @break
                                @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4h.09v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/> @break
                                @case('staff') <circle cx="9" cy="8" r="3"/><path d="M3 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M16 4.5a3 3 0 0 1 0 6M17 13a5 5 0 0 1 4 5v2"/> @break
                                @case('permissions') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 10h8M8 14h5"/> @break
                                @case('roles') <circle cx="8" cy="8" r="3"/><path d="M3 20v-2a5 5 0 0 1 10 0v2M16 7h5M18.5 4.5v5M15 15h6M15 19h4"/> @break
                                @case('manage-branches') <path d="M4 21V9l5-4 5 4v12M14 12l3-2 3 2v9M2 21h20M8 13h2M8 17h2M17 15h1"/> @break
                                @case('audit') <path d="M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3M9 3h6v4H9zM9 13l2 2 5-5"/> @break
                            @endswitch
                        </svg>
                        <span>{{ $label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
