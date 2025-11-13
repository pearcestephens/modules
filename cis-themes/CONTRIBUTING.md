# Contributing — CIS Themes

## Branching
- Create feature branches: `feature/theme-<short-name>`
- One feature per PR; squash merges preferred

## Permissions
- Theme editing requires `theme_admin` permission
- Do not write outside whitelisted paths

## Whitelisted Paths
- `/modules/base/themes/{active}/(views|assets/css|assets/js|components)`
- `/modules/cis-themes/themes/{active}/(views|assets/css|assets/js|components)`
- No writes outside these without explicit approval

## Commit Messages
- Use conventional commits:
  - feat(theme): Add button tokens editor
  - fix(component): Correct header spacing
  - chore(build): Update CSS build pipeline

## Code Style
- PSR-12 PHP
- ESLint/Prettier for JS/TS
- Stylelint for CSS/SCSS

## PR Checklist
- [ ] Lints pass (PHP/JS/CSS)
- [ ] PHP lint: `php -l` changed files
- [ ] Assets build completes without errors
- [ ] Screenshots or short clip for UI changes
- [ ] Docs updated (README or feature docs)

## Security
- No secrets in repo
- Keep APP_DEBUG off in prod
- Ensure CSRF on all write endpoints

## Archiving
- Move deprecated or experimental assets to `/modules/cis-themes/archived/{origin}/...`
- Update `archived/README.md` if needed
