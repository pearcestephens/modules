# Consignments Module - Changelog

All notable changes to this module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Hexagonal architecture structure (domain/infra/app)
- CODEOWNERS file for code review requirements
- Comprehensive README with quick start guide
- STATUS.md with completion tracking
- Roadmap.md aligned with strategic report
- MIGRATIONS.md for database change tracking
- docs/ADRs/, docs/API/, docs/Runbooks/ directories

### Changed
- Moved all markdown files to `/docs/` directory
- Moved dead/duplicate files to `/_trash/`
- Updated directory structure to support hexagonal architecture

### Deprecated
- None

### Removed
- ConsignmentService_BROKEN_SCHEMA.php (moved to trash)
- ConsignmentService_WORKING.php (moved to trash)

### Fixed
- None

### Security
- None (O4 in progress)

---

## [2.0.0-alpha] - 2025-11-01

### Added
- **O1 Complete:** Directory hygiene and baseline guardrails
  - Created target directory structure
  - Added CODEOWNERS for review enforcement
  - Created README, STATUS, ROADMAP documents
  - Consolidated documentation

### Context
This release marks the start of the comprehensive refactoring initiative following the "BOT MANDATE" specification. The module is being transformed from a loosely structured codebase into a production-grade hexagonal architecture system with proper separation of concerns.

---

## [1.9.0] - 2024-10-31 (Pre-Refactor Baseline)

### Added
- AI insights integration (GPT-4, Claude 3.5)
- Gamification system (points, achievements, leaderboards)
- Pack-pro interface with barcode scanning
- Freight integration (NZ Post, GoSweetSpot)
- Multi-tier approval workflow ($0-2k, $2k-5k, $5k+)

### Changed
- Enhanced receiving flow with variance tracking
- Improved signature capture
- Updated Lightspeed sync timing (at receive, not create)

### Fixed
- Various bug fixes in purchase order workflow
- Freight calculation edge cases

---

## [1.8.0] - 2024-10-15

### Added
- Initial consignments database schema
- Queue system infrastructure
- Basic Lightspeed integration
- Purchase order creation workflow

---

## Versioning Strategy

### Version Format: MAJOR.MINOR.PATCH[-PRERELEASE]

- **MAJOR:** Breaking changes (API contracts, database schema incompatibilities)
- **MINOR:** New features (backwards-compatible)
- **PATCH:** Bug fixes (backwards-compatible)
- **PRERELEASE:** alpha, beta, rc1, rc2, etc.

### Current Phase: 2.0.0-alpha
- Major version bump due to complete architecture refactor
- Alpha stage during O1-O13 implementation
- Beta stage after 90% test coverage achieved
- RC (Release Candidate) after production pilot
- GA (General Availability) after full rollout

---

## Change Categories

### Added
New features, functionality, or files

### Changed
Changes to existing functionality (backwards-compatible)

### Deprecated
Features marked for removal in future versions

### Removed
Features or files that have been deleted

### Fixed
Bug fixes

### Security
Security improvements or vulnerability fixes

---

## Commit Convention Reference

```
feat(consignments): Add idempotency keys to Lightspeed client
fix(consignments): Resolve status transition validation bug
sec(consignments): Remove hardcoded credentials
docs(consignments): Update webhook setup guide
test(consignments): Add integration tests for receiving flow
refactor(consignments): Extract status policy to domain layer
perf(consignments): Optimize queue worker batch processing
chore(consignments): Update dependencies
```

---

## Release Checklist

Before tagging a new release:

- [ ] All objectives complete per STATUS.md
- [ ] All tests passing (unit, integration, API, smoke)
- [ ] Documentation updated (STATUS, ROADMAP, API docs)
- [ ] MIGRATIONS.md includes all schema changes
- [ ] Security scan passed (no secrets, vulnerabilities)
- [ ] Performance benchmarks meet targets
- [ ] Staging deployment successful
- [ ] Rollback procedure tested
- [ ] Release notes drafted
- [ ] Stakeholders notified

---

## Support & Contribution

- **Report Issues:** GitHub Issues (pearcestephens/modules)
- **Request Features:** GitHub Issues with `enhancement` label
- **Submit Changes:** Pull Requests (requires review per CODEOWNERS)
- **Documentation:** See `/docs/CONTRIBUTING.md` (coming soon)

---

**Maintained by:** Pearce Stephens (@pearcestephens)
**Last Updated:** November 1, 2025
**Next Release:** 2.0.0-beta (estimated January 2026)
