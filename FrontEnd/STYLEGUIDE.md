# CND Manager — UI conversion guide (Tailwind v4)

The UI is deliberately **flat, quiet and static** — the visual language of an
international admin console (Linear / Vercel / Stripe dashboard), not a
marketing site.

## Non-negotiables

1. **No motion.** No `animate-*`, no `transition-*`, no `@keyframes`, no
   `transform` on hover, no `backdrop-blur`. `src/index.css` has a global
   kill switch, but do not author motion in the first place.
2. **No decorative shadows.** No `shadow-lg`/`shadow-xl`. Depth comes from
   `border border-line`. At most `shadow-sm` on a floating popover.
3. **One radius.** `rounded` (4px). Never `rounded-xl`, `rounded-2xl`.
   `rounded-full` only for avatars and status dots.
4. **No gradients**, no glow, no `::after` decoration blobs.
5. **RTL-safe.** Use logical properties: `ms-*`/`me-*`, `ps-*`/`pe-*`,
   `start-*`/`end-*`, `text-start`/`text-end`, `border-s`/`border-e`.
   Never `ml-*`, `mr-*`, `left-*`, `right-*`, `text-left`, `text-right`.

## Tokens (defined in `src/index.css` under `@theme`)

| Purpose | Class |
|---|---|
| Page background | `bg-canvas` |
| Card / panel background | `bg-surface` |
| Zebra / header fill | `bg-subtle` |
| Body text | `text-ink` |
| Secondary text | `text-muted` |
| Borders | `border-line`, `border-line-strong` |
| Brand (dark) | `bg-brand-800`, `text-brand-800` |
| Brand (accent) | `text-brand-500`, `bg-brand-50` |
| Status | `*-ok-*`, `*-warn-*`, `*-danger-*`, `*-info-*` (each has `-50` and `-500`) |

## Primitives — prefer these over re-inventing

Defined in `@layer components` in `src/index.css`. Compose them with utilities.

- Buttons: `btn btn-primary`, `btn btn-secondary`, `btn btn-ghost`,
  `btn btn-danger`, `btn-sm` modifier, `btn-icon` for square icon buttons.
- Forms: `field` (wrapper), `label`, `input`, `select`, `textarea`, `hint`,
  `error-text`.
- Surfaces: `card`, `card-head`, `card-body`, `panel`.
- Page: `page`, `page-header`, `page-title`, `page-subtitle`, `eyebrow`,
  `section-title`.
- Tables: `table-wrap` > `table` (style comes from `thead th` / `tbody td`).
- Badges: `badge badge-neutral|brand|ok|warn|danger|info`.
- Overlays: `overlay` (backdrop), `modal`, `modal-head`, `modal-body`,
  `modal-foot`, `drawer`.
- Scroll: styled globally (thin, transparent track, `line-strong` thumb) — no
  class needed. `scroll-dark` for a pane over a dark background,
  `scroll-hidden` when another control drives the scrolling.
- Misc: `empty-state`, `divider`, `muted`, `stat-grid`.

## Typography scale

Body `text-[13px]`, secondary `text-xs`, labels `text-xs font-semibold`,
section headings `text-sm font-semibold`, page title `text-xl font-semibold`.
Nothing larger than `text-2xl` outside the login screen.

## Spacing

Stick to `gap-2 / gap-3 / gap-4`, padding `p-3 / p-4`, page gutters `p-4 lg:p-6`.

## Reference implementations

- `src/Layout/AppShell.jsx` — sidebar, topbar, account menu.
- `src/Components/Feedback.jsx` — toast, empty state.

## Migration state

`src/legacy.css` is the pre-Tailwind stylesheet, still imported by
`src/main.jsx`. Every converted file should stop depending on it. Once no
`className` in `src/` references a legacy class, delete both the file and its
import from `main.jsx`.
