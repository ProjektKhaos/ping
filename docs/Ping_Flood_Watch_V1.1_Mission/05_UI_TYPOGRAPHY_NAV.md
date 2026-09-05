# Mission Detail — Mobile Typography & Colorful Bottom Navigation

**User request:** Make the smallest fonts a little larger and make the menu icons more colorful and visually attractive.

Do this as a polish pass, not a redesign.

---

# 1. Typography goal

The current UI contains several 9–11 px user-facing text sizes.

Raise the small-text floor so the app is easier to read on a phone.

Do not make the layout bulky.

---

# 2. Specific current CSS to review

`assets/css/app.css` currently contains examples such as:

```text
.brand-copy small                  10px
.eyebrow / .kicker                 10px
.badge                             10px
.metric-grid span                  10px
.station-row small                 11px
.range-note                        11px
.attribution                       11px
.forecast-horizons span             9px
.forecast-horizons strong          10px
.updated                           11px
.threshold-list a                 11px
.page-footer                       10px
.bottom-nav a                      10px
chart ticks in JS                   9px
@media max-width:370 metric span    9px
```

---

# 3. Target sizes

Use these as starting targets, adjusting slightly if needed to avoid overflow:

```text
brand tagline                     11.5–12px
eyebrow/kicker                    11–12px
badges                            11px
metric labels                     11.5–12px
station secondary text            12px
range/attribution text            12px
forecast horizon labels           11px
forecast horizon values           11.5–12px
updated timestamp                 12px
threshold source links            12px
footer                            11.5–12px
bottom nav labels                 11.5–12px
chart ticks                       11px
```

Avoid visible UI text below approximately `11px`.

For Thai, ensure the chosen size remains comfortably readable.

---

# 4. Preserve hierarchy

Do not enlarge everything equally.

Keep:

- main level reading dominant,
- h1 prominent,
- risk headings clear,
- metadata secondary.

The purpose is to improve legibility of tiny text, not flatten the hierarchy.

---

# 5. Forecast card

The four forecast horizon cells are compact.

After font increase:

- ensure 6h / 12h / 24h / 48h still fit at 360 px,
- values like `123.4–145.7 mm` must not break the card badly,
- use responsive wrapping or slightly wider vertical layout if necessary,
- do not shrink the font back to 9px.

---

# 6. Chart ticks

Current chart ticks use 9 px in:

```text
assets/js/home.js
assets/js/station.js
```

Increase to approximately:

```text
11px
```

Reduce tick count if needed rather than making text tiny.

---

# 7. Bottom navigation visual polish

Current nav:

```text
Home
Stations
Alerts
```

with monochrome Material Symbols.

Keep the same three destinations.

Use existing Material Symbols Rounded.

Recommended icons:

```text
Home      home
Stations  waves or water_drop
Alerts    notifications_active
```

Do not introduce external icon libraries.

---

# 8. Color direction

Give each nav icon its own subtle identity:

```text
Home:
blue

Stations:
cyan / teal

Alerts:
amber / coral
```

Suggested concept:

```text
inactive icon:
soft colored rounded background
colored icon

active item:
slightly stronger colored icon background
stronger label color
subtle tinted item surface
```

Do not make inactive icons grey-only.

Do not use saturated red for Alerts unless it represents an actual warning state.

---

# 9. Suggested markup

Prefer explicit nav classes/data attributes rather than fragile `nth-child` styling.

Example:

```html
<a class="nav-item nav-home" ...>
    <span class="nav-icon"><span class="material-symbols">home</span></span>
    <span class="nav-label">Home</span>
</a>
```

Likewise:

```text
nav-stations
nav-alerts
```

This makes styling maintainable.

---

# 10. Icon containers

Suggested size:

```text
32–36px icon bubble
20–23px symbol inside
```

Touch target remains at least:

```text
44 × 44px
```

Do not reduce the current nav touch target.

---

# 11. Alert-aware icon enhancement

Optional but desirable:

If an active warning exists, Alerts nav may display a small badge/dot.

Example:

```text
●
```

Do not show a red dot when there is no active alert.

If this requires invasive global querying on every page, skip it unless cleanly available.

---

# 12. Accessibility

- color must not be the only active-state signal,
- retain `aria-current="page"`,
- ensure label contrast,
- icons remain `aria-hidden` if labels provide meaning,
- forced-colors mode must remain usable,
- touch target test must still pass.

---

# 13. Mobile QA

Test:

```text
360 × 800
390 × 844
430 × 932
```

No horizontal overflow.

Bottom nav must remain visually balanced.

Thai labels must fit.
