# Consignments Module Archive - 2025-11-10

Archived non-essential UI/demo/backup files to reduce clutter and focus active development.

## Categories
1. View Backups (`views/*.backup.*`): Timestamped snapshots kept for reference only.
2. Theme Demo Gallery (`themes-demo/`, `THEME_GALLERY.php`): Prototype layout/theme exploration assets.
3. Purple Home Backup (`home.php.purple_backup_20251105`): Color variant experiment.
4. Visual Test Suite (`VISUAL_TEST_SUITE.html`): Manual visual regression harness (unused in CI).

## Rationale
- None of these files are referenced by production code paths after grep verification.
- Consolidation improves navigation and reduces accidental edits of stale files.
- Restorable by copying back if a design element is needed.

## Restore Instructions
Copy required file from this archive path back to its original location relative to `consignments/` root.

Example:
```
cp cons...