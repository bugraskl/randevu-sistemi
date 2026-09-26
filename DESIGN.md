---
name: Randevu Yönetim Sistemi
description: Seans Odası — a therapist's own consulting room in daylight; the clock is always visible and today leads.
colors:
  wool: "#4B1D35"
  wool-deep: "#3A1629"
  wool-hover: "#5A2441"
  wool-soft: "#F0E3EA"
  on-wool: "#F5F0F2"
  on-wool-2: "#CDB3C0"
  accent-text: "#6E2A4E"
  brass: "#B98A3E"
  brass-ink: "#7E5B1F"
  brass-soft: "#F2E8D3"
  brass-on-wool: "#D7B06A"
  madder: "#A8392B"
  madder-soft: "#F6E2DD"
  madder-on-ink: "#F0A090"
  wall: "#EDEBEA"
  wall-2: "#E3E0DF"
  surface: "#FBFAFA"
  surface-2: "#F4F2F1"
  line: "#D9D5D4"
  line-strong: "#BDB7B6"
  ink: "#231B1E"
  ink-2: "#4B4246"
  ink-3: "#6A6064"
typography:
  display:
    fontFamily: "Figtree, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
    fontSize: "clamp(3.5rem, 18vw, 4.75rem)"
    fontWeight: 650
    lineHeight: 0.95
    letterSpacing: "-0.035em"
    fontFeature: "'tnum' 1"
  headline:
    fontFamily: "Figtree, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Figtree, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  row-time:
    fontFamily: "Figtree, system-ui, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 650
    lineHeight: 1.2
    letterSpacing: "-0.01em"
    fontFeature: "'tnum' 1"
  body:
    fontFamily: "Figtree, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
    fontFeature: "'cv11' 1"
  label:
    fontFamily: "Figtree, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 600
    lineHeight: 1.2
rounded:
  sm: "10px"
  field: "12px"
  md: "14px"
  lg: "20px"
  pill: "999px"
spacing:
  s-1: "4px"
  s-2: "8px"
  s-3: "12px"
  s-4: "16px"
  s-5: "20px"
  s-6: "24px"
  s-7: "32px"
  s-8: "40px"
  s-9: "56px"
components:
  button-primary:
    backgroundColor: "{colors.wool}"
    textColor: "{colors.on-wool}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "11px 18px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.wool-hover}"
    textColor: "{colors.on-wool}"
  button-primary-active:
    backgroundColor: "{colors.wool-deep}"
    textColor: "{colors.on-wool}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "11px 18px"
    height: "44px"
  button-destructive-confirm:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.wall}"
    rounded: "{rounded.pill}"
    padding: "11px 18px"
    height: "44px"
  button-destructive-confirm-hover:
    backgroundColor: "{colors.ink-2}"
    textColor: "{colors.wall}"
  button-quiet:
    backgroundColor: "transparent"
    textColor: "{colors.accent-text}"
    rounded: "{rounded.pill}"
  button-quiet-hover:
    backgroundColor: "{colors.wool-soft}"
    textColor: "{colors.accent-text}"
  button-on-wool-solid:
    backgroundColor: "{colors.on-wool}"
    textColor: "{colors.wool}"
    rounded: "{rounded.pill}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    padding: "11px 14px"
    height: "48px"
  chip:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-2}"
    rounded: "{rounded.pill}"
    padding: "0 16px"
    height: "36px"
  chip-active:
    backgroundColor: "{colors.wool}"
    textColor: "{colors.on-wool}"
  row-item:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    padding: "12px 16px"
    height: "64px"
  tab-active:
    backgroundColor: "{colors.wool-soft}"
    textColor: "{colors.accent-text}"
    rounded: "{rounded.pill}"
    width: "52px"
    height: "30px"
  tab-new:
    backgroundColor: "{colors.wool}"
    textColor: "{colors.on-wool}"
    rounded: "16px"
    width: "52px"
    height: "40px"
  toast:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.wall}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
  alert-warning:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    padding: "12px 16px"
  alert-danger:
    backgroundColor: "{colors.madder-soft}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    padding: "12px 16px"
  due-strip:
    backgroundColor: "{colors.madder-soft}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "16px"
---

# Design System: Randevu Yönetim Sistemi

## Overview

**Creative North Star: "Seans Odası" (the consulting room in daylight)**

The app is one therapist's own room, seen from a phone between sessions. The ground is a pale wall-grey. Whole regions are upholstered in plum wool (the user-pinned mürdüm): the now/next panel on Bugün, the desktop rail, the login hero and the raised centre tab. Text is walnut ink. Brass is the room's clock: it marks time and state and never fills a button. Kilim madder is a single warning thread that means "ödenmedi" (unpaid).

Density is calm but working: rows are 64px tall, times and amounts sit in one tabular column, and summaries are a single ledger line. Nothing is a stat card, a wide table on the phone, a hamburger sidebar or a floating action button. The phone at 390px wide is the design. Desktop widens the same system: the tab bar becomes a wool rail and Bugün splits into two columns.

Motion uses a single timing family that eases out like a heavy curtain. Sheets rise from the bottom on phone. The session ring and the now-line are the only continuous animations, and both are tied to real time.

**Key Characteristics:**
- Plum wool fills whole regions, with a faint felt grain. It is not scattered as small accents.
- One humanist sans (Figtree). Every time, amount and count uses tabular numerals.
- Brass means status, madder means unpaid, and ink carries every destructive action.
- Every list is a sequence of rows with a fixed time/amount column.
- Actions say what they do in words ("Ara", "Ödeme al", "Danışan kartı", "Menü").
- Light theme ("gündüz") leads. The dark theme ("akşam") keeps the same roles.

## Colors

The palette is a warm neutral room with one dominant plum, one brass status metal and one madder alarm. Neutrals carry a faint plum-warm tint.

### Primary
- **Mürdüm Wool** (wool): the brand colour, pinned by the user. It fills the now panel, the desktop rail, the login hero, the centre "+ Randevu" tab, every primary button (including every money action such as "Ödeme al"), the active filter chip, the active pagination page, today's calendar number, checked switches and checkboxes, and the light-theme field focus border. Its surface always carries the felt grain.
- **Deep Wool / Wool Hover** (wool-deep, wool-hover): the pressed and hover states of wool fills, nothing else.
- **Wool Tint** (wool-soft): the active tab pill, the current row on Bugün, the selected calendar day, the quiet-button hover, the selected option in a `.choice` group, avatars, and confirmed-state badges.
- **Plum Ink** (accent-text): links, quiet text buttons, the plum-dot mark text ("Seansta", "Aktif", "Etkin") and the active-tab glyph on light surfaces.
- **On-Wool / On-Wool Muted** (on-wool, on-wool-2): primary and secondary text on wool. Secondary text on wool is never plain grey.

### Secondary
- **Brass** (brass, brass-ink, brass-soft, brass-on-wool): status only. It appears on the session ring stroke, the now-line and its time label, the current node on the day rail, the "ödendi" seal, the countdown emphasis in the now panel, the brand mark ring on wool, and focus outlines (`--focus` is brass-ink in light, brass in dark). The dark-theme field focus border is also brass. Use brass-on-wool on plum and brass-ink for text on light surfaces.

### Tertiary
- **Kilim Madder** (madder, madder-soft): means unpaid, and is also the error colour. For unpaid it marks the "Ödenmedi" hollow-ring mark, the unpaid strip on Bugün (madder-soft ground, madder count ring) and the due value in a ledger line. For errors it marks form error text and borders (`.invalid-feedback`, `.is-invalid`), the error alert (madder-soft ground, madder icon, e.g. the login failure) and `.text-danger`.
- **Madder on Ink** (madder-on-ink): the error/warning icon inside an ink toast. It is lighter in the light theme and becomes #A8392B in dark, where the toast inverts to a light fill.

### Neutral
- **Wall / Wall Deep** (wall, wall-2): the page ground, the segmented-control well, the pressed row state.
- **Surface / Surface Muted** (surface, surface-2): list and panel faces, sheet bodies, the hover state, field-box and sheet-summary grounds.
- **Line / Line Strong** (line, line-strong): row dividers, panel borders, the day-rail spine (line), field and chip strokes, the sheet grab handle (line-strong).
- **Walnut Ink** (ink, ink-2, ink-3): primary text and the destructive-confirm fill (ink), body copy and outline-destructive text (ink-2), meta, labels, past rows and the hollow off-state ring (ink-3).

Dark theme ("akşam", `body.dark`) remaps the same roles: wall #151113, surface #1E181B, ink #EEEAEC, brass #CFA356, madder #E0806D, madder-on-ink #A8392B, accent-text #DDA9C3. Wool stays #4B1D35.

### Named Rules
**The Brass Is Status Rule.** Brass never fills a button or any other action. It only marks time and state: the ring, the now-line, the current rail node, focus and the paid seal.

**The Madder Means Unpaid Rule.** Madder has exactly two uses: "ödenmedi" and errors (form validation, the error alert, the error toast icon). Warnings, deletions, cancellations, logout ("Çıkış yap") and neutral badges are not unpaid and not errors, so they use ink and neutrals.

**The Regions Not Accents Rule.** Plum fills whole regions (a panel, a rail, a raised tab, a primary button). If plum shows up as a scattered border or a decorative stripe, it has been misused.

## Typography

**Display Font:** Figtree (self-hosted latin + latin-ext woff2, weights 400–700), falling back to system-ui
**Body Font:** Figtree
**Label/Mono Font:** Figtree with tabular numerals. There is no separate mono.

**Character:** A single friendly humanist sans with high weight contrast. Clock-scale numerals in display sizes sit beside plain 16px body text. `font-feature-settings: "cv11"` is set on body.

### Hierarchy
- **Display** (650, clamp(3.5rem, 18vw, 4.75rem), 0.95): the next-session time in the now panel. The login clock uses 4rem, or 6rem on desktop. Tabular numerals only.
- **Headline** (700, 1.375rem, 1.15; 1.625rem ≥992px): the app-bar page title ("Bugün", "Kasa"), with a 0.8125rem ink-3 subline for the date or period.
- **Title** (700, 1.125rem, 1.2): section titles ("Günün programı", "Ödeme bekleyenler"). Modal and sheet titles use 1.25rem/700. The client name in the now panel uses 1.375rem/650.
- **Row time** (650, 1.0625rem, tabular): the leading time column of every row. A 0.75rem ink-3 date sits under it when needed.
- **Body** (400, 1rem, 1.5): paragraphs in ink-2. Row titles are 1rem/600 in ink.
- **Label** (600, 0.8125rem): state marks, form labels (0.875rem/600 ink-2), row meta (0.8125rem ink-3), table heads. Labels are sentence case and never uppercase.

### Named Rules
**The Tabular Clock Rule.** Every time, amount, count and date number uses tabular numerals (`.num`, `.money`, `time`, `.tnum`, row-time, ledger values), so columns line up in every list at every width.

**The 16px Field Rule.** Inputs are 1rem, so iOS never zooms on focus.

## Layout

The layout is phone-first, single column. Content sits in `.page` (max-width 1180px, narrow variant 720px) with a 16px gutter. Sections are separated by 32px (s-7), and a section head sits 12px above its list. A sticky, translucent app bar (56px, blurred wall at 88%) gains a hairline border once the page scrolls. A fixed five-slot tab bar (64px plus safe-area) holds Bugün, Randevular, the raised "Randevu" (+) action, Danışanlar and Kasa. Content gets bottom padding to clear the tab bar. "Menü" in the app bar opens a bottom menu sheet for the remaining pages and for logout.

At ≥992px the tab bar disappears and a sticky 252px wool rail takes its place (brand mark, "Yeni randevu", grouped links, user and theme at the foot). Bugün becomes a 5fr / 7fr grid: now, unpaid and upcoming on the left; the day's programme spans the right column. On phone, the Bugün app bar merges into the wool panel, so the top ~40% of the screen is one plum region.

**The Time Scale Rule.** Bugün's day list is a time scale, not a list. `.day-rail` draws a 1px spine between the time column and the names, and each session is a node on it (hollow = upcoming, filled line-strong = past, brass with a halo = current). Free time between sessions (≥15 min) renders as `.day-gap`. Its height is proportional to the minutes (0.5px per minute, clamped 28–120px), it is hatched with faint 15px hour lines, and it carries a pill label ("3 sa 10 dk boş"). The brass now-line sits at its real position inside the gap.

**The Ledger Line Rule.** Summary numbers are a single `.ledger` line of label/value pairs between two hairlines ("Eylül toplamı ₺150.000 · Nakit + Havale/EFT ₺94.000 · Kart ₺56.000"). The lead item can take a full line at 1.125rem. Summaries are never stat cards.

Tables use `.table-stack` below 768px, which turns each row into a stacked label/value block.

## Elevation & Depth

The room is mostly flat and tonal. Wall, surface and wool fills separate layers, and hairline borders carry structure. Shadows are small and warm-tinted, and sheets rise from below.

### Shadow Vocabulary
- **Rest** (`box-shadow: 0 1px 2px rgba(34,28,23,0.06), 0 1px 1px rgba(34,28,23,0.03)`): lists, panels, cards, the active segment. It barely registers and only seats the surface on the wall.
- **Lift** (`box-shadow: 0 10px 28px -10px rgba(34,28,23,0.22), 0 2px 6px rgba(34,28,23,0.06)`): desktop modals, dropdowns, Select2 menus, toasts.
- **Sheet** (`box-shadow: 0 -12px 40px -12px rgba(23,20,16,0.28)`): bottom sheets and the offcanvas menu on phone.
- **Raised tab** (`box-shadow: 0 8px 18px -6px rgba(58,22,41,0.55), 0 1px 3px rgba(23,20,16,0.12)`): only the centre "+ Randevu" tab, which rises 14px out of the bar.

### Named Rules
**The Heavy Curtain Rule.** All motion uses one easing curve, `cubic-bezier(0.2, 0.8, 0.2, 1)`, at 160ms, 240ms or 320ms. On phone, sheets slide up from translateY(100%) over 320ms with a 40×5px grab handle. On desktop, modals settle from translateY(16px) scale(.985). `prefers-reduced-motion` collapses all of it.

## Shapes

Corners are soft, and every action is a pill. The radius steps are 10px (small controls, rail links), 12px (fields, choice tiles, field boxes, alerts, dropdowns), 14px (lists, panels, the unpaid strip) and 20px (sheets, modals, the now panel's bottom corners, the login hero). Buttons, chips, segmented controls, tab pills, pagination and search fields are full pills (999px). Circles are reserved for meaning: avatars, the brand mark, state marks, the due-count ring, rail nodes and the today number in the calendar. Borders are 1px hairlines. Empty states use a dashed line-strong border.

## Components

### Buttons
Every action is labelled in words, with an optional leading icon.
- **Shape:** full pill (999px). Minimum height 44px (sm 36px, lg 52px). 0.9375rem/600. Pressing scales to 0.98.
- **Primary:** wool fill with on-wool text. Hover wool-hover, pressed wool-deep. This includes every money action ("Ödeme al", "Ödemeyi kaydet"). The Bootstrap success/warning variants are remapped to this same plum.
- **On wool:** the translucent `.btn-on-wool` ("Ara", "Danışan kartı" in the now panel) and `.btn-on-wool-solid` (light fill, plum text) when a primary is needed on plum.
- **Secondary:** surface fill, line border, ink text ("Vazgeç").
- **Quiet:** transparent with plum-ink text and a wool-soft hover, used for section links like "Tüm randevular" and "Takvim". The `quiet-ink` variant is for low-weight text actions such as "Randevuyu sil".
- **Destructive:** neutral ink. In row sheets and forms it is an outline (line-strong border, ink-2 text). The final confirm in a delete sheet is an ink fill with wall text. The sentence in the sheet carries the warning.
- **Focus:** 2px brass-ink outline at 2px offset, switching to brass-on-wool on plum. No glow rings.

### Chips
- **Style:** 36px pill, surface fill, line-strong border, ink-2 0.875rem/600. They scroll horizontally and bleed to the gutter.
- **State:** active is a full wool fill with on-wool text. Hover darkens the border to ink-3.

### Segmented control
A wall-2 pill well with a 3px inset ("Liste / Takvim", "Ödemeler / Giderler"). The active segment is a surface pill with the rest shadow. Bootstrap nav-tabs are restyled to the same control.

### Cards / Containers
- **Corner Style:** 14px.
- **Background:** surface on the wall.
- **Shadow Strategy:** rest shadow only (see Elevation). Hovering does not lift.
- **Border:** 1px line.
- **Internal Padding:** 16px.

### Inputs / Fields
- **Style:** 48px tall, surface fill, 1px line-strong stroke, 12px radius, 1rem text, ink-3 placeholder. Search fields are pills with a leading icon. Select2 matches exactly.
- **Focus:** the border turns wool with a 3px wool ring at 22%. In dark theme it is brass at 25%.
- **Error / Disabled:** errors get a madder border and 0.8125rem madder text. Valid fields stay neutral and are never green. Disabled and readonly fields use surface-2 with ink-3 text.
- **Quick choice (`.choice`):** a 3-up grid of 64px tiles for amount and payment method. The checked tile gets a wool border, a wool-soft fill and an inset 1px wool ring.

### Navigation
- **Phone tab bar:** five equal slots on translucent surface (94%, blur 16px) with a hairline top border and 0.6875rem/600 labels. The active tab gets a 52×30 wool-soft pill behind its icon, with plum-ink icon and ink label. The centre "Randevu" tab is a raised 52×40 wool block with a 16px radius, felt grain and the raised-tab shadow. It is the only new-appointment entry, and there is no floating action button.
- **Desktop rail:** a wool region with felt and 42px links in on-wool-2. Hover is a 8% light wash, and active is a 13% wash with a brass-on-wool icon. Group labels are 0.75rem/600, sentence case.
- **Menu sheet:** a bottom offcanvas holding a user header and a grouped `.menu-list` of 52px rows with chevrons.

### Rows and the row-actions sheet (signature)
Every list is a stack of `.row-item`s: a 3.4rem tabular time column, then the title with a meta line under it (a state mark when the session has one, otherwise the formatted phone), then a trailing amount or a single primary action. Rows are separated by hairlines inside a 14px surface list. On list pages a row shows at most one inline action (for example "Ara" or "Ödeme al"). Secondary and destructive actions ("Düzenle", "İptal et", "Sil") move into a row-actions bottom sheet, which opens when the row is tapped. The sheet shows the row title, a meta line ("11:00 · Ödendi · Havale/EFT") and a `.menu-list` of labelled actions. Cancelled rows strike the name through, past rows fade to ink-3, and the current row gets wool-soft.

### State marks (signature)
Each mark is a 0.8125rem/600 label led by a shape. Appointments have no confirmation step (confirmation happens outside the app), so there is no "Onay bekliyor" or "Onaylı" anywhere.

Session states:
- **ödendi ("Ödendi"):** a 14px brass seal with an ink check, text in brass-ink.
- **ödenmedi ("Ödenmedi"):** a 12px hollow madder ring, text in madder.
- **iptal ("İptal edildi"):** struck-through ink-3 text with no shape.
- **Seansta ("Seansta · 15:50’e kadar"):** a solid plum dot, text in plum ink. Only the in-progress session on Bugün gets it.
- **Upcoming:** no mark. The meta line shows the formatted phone ("0500 123 45 67"). On client-details the history row reads the plain word "Planlandı", with no shape.

Non-appointment on/off states reuse the two neutral shapes: a solid plum dot for on ("Aktif" in user management, "Etkin" for recurring expenses, "Yanıtınız alındı" on the public confirmation page) and a hollow ink-3 ring for off ("Pasif", "Durduruldu").

**The Four Session States Rule.** Appointments have no confirmation step, so a session carries exactly four states: ödendi (brass seal), ödenmedi (madder ring), iptal (strike-through) and "Seansta" (plum dot, the in-progress session only). A future session shows no mark; its meta line is the formatted phone number (e.g. "0500 123 45 67"). The hollow ring and plum dot also serve non-appointment on/off states (Aktif/Pasif, Etkin/Durduruldu), and are never used for a confirmation state.

### Now panel and session ring (signature)
The Bugün now panel is a wool region: app-bar title, display-size next time, client name, and a brass-on-wool countdown line ("1 saat 41 dakika sonra başlıyor"). The on-wool actions "Ara" and "Danışan kartı" sit below. A day summary ("7 seans bugün · 2 tamamlandı · 5 kaldı") sits above a wool hairline. To the right, a 104px ring (128px on desktop) has a 6px faint track, 2px tick marks and a brass-on-wool stroke. The stroke fills live toward the start time, or through the running session, and animates stroke-dashoffset over 900ms.

### Feedback (alerts, toasts, badges)
- **Alerts:** 12px radius, 12px/16px padding, 0.9375rem. The neutral and warning alert uses a surface-2 ground, line-strong border and ink-2 icon; this includes the "geri alınamaz" notice in delete sheets. The error alert (`.alert-danger`) uses a madder-soft ground, a 28% madder border and a madder icon. Success uses brass-soft, and info uses wool-soft.
- **Toasts:** an ink strip with wall text (`background: var(--ink)`, `color: var(--wall)`), so it inverts automatically in the dark theme. It uses a 14px radius and the lift shadow, and sits centred above the tab bar on phone and bottom-right on desktop. The success icon is brass (brass-on-wool in light, brass-ink in dark). The error/warning icon is madder-on-ink.
- **Badges:** pill, 0.75rem/600. Success is brass-soft with brass-ink, primary/info is wool-soft with plum ink, and every other variant, warning and danger included, is wall-2 with ink.

### Menu sheet rows
`.menu-list` rows are 52px with a leading icon, a label and a chevron; the active row is wool-soft. The logout row "Çıkış yap" is neutral (ink-2 text, ink-3 icon), like any other row.

### Unpaid strip
The unpaid strip is a disclosure (`details`) with a madder-soft ground and a 30% madder border. It has a 40px madder count ring, the title "N seans ödeme bekliyor" and client names in ink-2. Opening it reveals the unpaid rows.

## Do's and Don'ts

### Do:
- **Do** fill every primary action, money actions included, with mürdüm wool (#4B1D35) and the on-wool text.
- **Do** keep brass for status: the session ring, now-line, current rail node, focus outline and "ödendi" seal.
- **Do** limit session state to ödendi, ödenmedi, iptal and "Seansta". Leave upcoming sessions unmarked, with the formatted phone in the meta line.
- **Do** lead every row with the 3.4rem tabular time column, and right-align amounts in the trailing column.
- **Do** move secondary and destructive row actions into the row-actions sheet, leaving at most one labelled action inline.
- **Do** write summaries as one `.ledger` line between hairlines.
- **Do** render Bugün's programme as a time scale, with gap height proportional to free minutes and the brass now-line at the real time.
- **Do** open add and edit flows as bottom sheets on phone (20px top radius, grab handle, 320ms curtain easing).
- **Do** label actions in Turkish words ("Ara", "Ödeme al", "Menü"). An icon may accompany a label but never replaces it.

### Don't:
- **Don't** add a confirmation state ("Onay bekliyor", "Onaylı") to appointments. The hollow ring and plum dot are for non-appointment on/off states and the in-progress session only.
- **Don't** fill a button with brass, and don't colour a money action differently from other primaries.
- **Don't** use madder for delete, cancel, logout or warnings. Destructive actions are an ink outline, the final confirm is an ink fill, and warnings are a neutral surface-2 box. Madder is for "ödenmedi" and errors only.
- **Don't** hard-code toast or feedback colours. Toasts use ink and wall tokens so they invert with the theme.
- **Don't** build stat cards or KPI tiles. A number belongs in a ledger line or a row.
- **Don't** add a floating action button. New appointments come from the raised centre tab on phone and "Yeni randevu" in the desktop rail.
- **Don't** show wide tables on phone. Use rows, or `.table-stack` below 768px.
- **Don't** introduce green for success or validity. Paid is brass, and valid fields stay neutral.
- **Don't** scatter plum as borders, stripes or small accents. It fills whole regions.
- **Don't** use uppercase, letter-spaced kicker or eyebrow labels above titles.
