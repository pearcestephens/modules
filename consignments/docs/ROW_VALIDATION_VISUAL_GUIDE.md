# Row Validation - Quick Visual Guide

## Before vs After

### BEFORE (Input Only)
```
┌──────────────────────────────────────────────────────┐
│  Img │ Product Name     │ Stock │ Planned │ Counted │
├──────────────────────────────────────────────────────┤
│  🖼️  │ Product 1        │  50   │   10    │ [10]✓  │  ← Only input green
│  🖼️  │ Product 2        │  30   │   10    │ [8]⚠   │  ← Only input yellow
│  🖼️  │ Product 3        │  75   │   15    │ [20]❌ │  ← Only input red
└──────────────────────────────────────────────────────┘
```
❌ Hard to scan  
❌ Small visual target  
❌ Easy to miss validation state  

---

### AFTER (Entire Row)
```
┌──────────────────────────────────────────────────────┐
│  Img │ Product Name     │ Stock │ Planned │ Counted │
├──────────────────────────────────────────────────────┤
│█ 🖼️  │ Product 1        │  50   │   10    │   10   │  ← ENTIRE ROW GREEN
├──────────────────────────────────────────────────────┤
│█ 🖼️  │ Product 2        │  30   │   10    │    8   │  ← ENTIRE ROW YELLOW
├──────────────────────────────────────────────────────┤
│█ 🖼️  │ Product 3        │  75   │   15    │   20   │  ← ENTIRE ROW RED
├──────────────────────────────────────────────────────┤
│  🖼️  │ Product 4        │  40   │    5    │        │  ← ENTIRE ROW WHITE
└──────────────────────────────────────────────────────┘
     ▲
     └─ 4px colored left border
```
✅ Easy to scan at a glance  
✅ Large visual target  
✅ Impossible to miss validation state  
✅ Professional Bootstrap colors  

---

## Color Meanings

### 🟢 GREEN = Perfect ✓
```
Counted = Planned
Example: Planned 10, Counted 10 ✓
Action: None needed - correct!
```

### 🟡 YELLOW = Under-count ⚠️
```
Counted < Planned
Example: Planned 10, Counted 8
Action: Check if intentional (damaged/missing items)
```

### 🔴 RED = Over-count ❌
```
Counted > Planned
Example: Planned 10, Counted 12
Action: MUST FIX - Cannot pack more than planned
```

### ⚪ WHITE = Not Started
```
No value entered
Example: Planned 10, Counted [empty]
Action: Enter counted quantity
```

---

## Real-World Scenarios

### Scenario 1: Perfect Packing
```
Product A: Planned 10 → Counted 10 → 🟢 GREEN
Product B: Planned 5  → Counted 5  → 🟢 GREEN
Product C: Planned 15 → Counted 15 → 🟢 GREEN
───────────────────────────────────────────────
Result: All green, ready to ship! ✓
```

### Scenario 2: Damaged Items
```
Product A: Planned 10 → Counted 10 → 🟢 GREEN
Product B: Planned 5  → Counted 3  → 🟡 YELLOW (2 damaged)
Product C: Planned 15 → Counted 15 → 🟢 GREEN
───────────────────────────────────────────────
Result: Check yellow row - add note about damaged items
```

### Scenario 3: Counting Error
```
Product A: Planned 10 → Counted 10 → 🟢 GREEN
Product B: Planned 5  → Counted 8  → 🔴 RED (typo!)
Product C: Planned 15 → Counted 15 → 🟢 GREEN
───────────────────────────────────────────────
Result: Red row blocks submission - fix the count!
```

### Scenario 4: Work In Progress
```
Product A: Planned 10 → Counted 10 → 🟢 GREEN
Product B: Planned 5  → Counted    → ⚪ WHITE (not done)
Product C: Planned 15 → Counted    → ⚪ WHITE (not done)
───────────────────────────────────────────────
Result: 1/3 complete - continue packing
```

---

## Quick Reference Card

| State | Color | Bootstrap Class | Meaning | Action |
|-------|-------|----------------|---------|--------|
| ✓ Match | 🟢 Green | `table-success` | Perfect | None |
| ⚠️ Under | 🟡 Yellow | `table-warning` | Short | Verify |
| ❌ Over | 🔴 Red | `table-danger` | Invalid | Fix |
| ○ Empty | ⚪ White | (none) | Pending | Count |

---

## Staff Training

### How to Use:

1. **Start Counting:**
   - All rows are white (nothing entered yet)

2. **As You Count:**
   - Type quantity in "Counted" column
   - Watch the row change color instantly

3. **Green Rows:**
   - ✓ Perfect! Move to next item

4. **Yellow Rows:**
   - ⚠️ Less than planned - is this correct?
   - Did items get damaged/lost?
   - Add note if intentional

5. **Red Rows:**
   - ❌ Stop! You cannot pack more than planned
   - Check if you typed wrong number
   - Fix immediately before continuing

6. **Before Submitting:**
   - Scan screen quickly
   - Mostly green? Good! ✓
   - Any red? Must fix first! ❌
   - Yellow? Add notes explaining why

---

## Tips for Fast Scanning

### From Across the Room:
- **Green dominates** = Good progress ✓
- **Yellow scattered** = Minor issues, manageable ⚠️
- **Red anywhere** = Stop and fix ❌
- **Mostly white** = Just started

### Quick Mental Math:
```
10 items total:
- 7 green  = 70% done, looking good
- 2 yellow = 20% have issues, check notes
- 1 red    = 10% need fixing
- 0 white  = 100% counted (done!)
```

---

## Print View (Clean)

In print, **all colors are removed**:
```
┌─────────────────────────────────────────────────────┐
│  ✓  │ Product Name     │ Stock │ Planned │ Counted │
├─────────────────────────────────────────────────────┤
│  ☐  │ Product 1        │  50   │   10    │ _______ │  ← WHITE
│  ☐  │ Product 2        │  30   │   10    │ _______ │  ← WHITE
│  ☐  │ Product 3        │  75   │   15    │ _______ │  ← WHITE
└─────────────────────────────────────────────────────┘
```
- Clean, professional appearance
- No color ink wasted
- Black and white only
- Staff write quantities manually

---

**End of Quick Guide**
