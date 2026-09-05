# Detail Mission — Upstream Signal Semantics

---

# 1. Goal

Make upstream monitoring useful even while official/configured rapid-rise thresholds remain disabled.

---

# 2. Separate factual trend from risk classification

Trend:

```text
falling
stable
rising
unknown
```

Risk signal:

```text
normal
rising_fast
watch
warning
unknown
```

A factual positive change may produce:

```text
rising
```

without producing:

```text
watch
```

---

# 3. Suggested factual trend rule

Do not invent a flood threshold.

A factual trend may be determined using measurement direction with a very small noise/deadband rule already used elsewhere in the app, if available.

If no safe existing deadband exists, display numerical change and arrow without assigning a named `rising` classification.

Example:

```text
↑ +8 cm / 1h
```

is always preferable to inventing:

```text
RISING FAST
```

without configured criteria.

---

# 4. Threshold-based early warning

Only enable:

```text
RISING FAST
WATCH
WARNING
```

when corresponding configured upstream thresholds are non-null.

`null` means disabled.

---

# 5. UI

Examples:

```text
P.67
0.81 m
↑ +8 cm / 1h
Rising
```

With configured threshold crossed:

```text
P.67
0.99 m
↑ +24 cm / 1h
Rising fast
```

Use color plus text/icon.

---

# 6. No arrival-time claims

Do not display:

```text
will reach P.1 in 6 hours
```

unless a future verified model explicitly supports that prediction.
