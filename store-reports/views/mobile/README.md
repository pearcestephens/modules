# Store Reports Mobile UI

## 📱 Mobile Interface for Store Managers

### Pages Created:
1. **create-report.php** - Main report creation form with camera/voice
2. **report-view.php** - View completed report (TODO)
3. **ai-chat.php** - AI assistant chat interface (TODO)
4. **my-reports.php** - List of user's reports (TODO)

### Features Implemented:
- ✅ Mobile-first responsive design
- ✅ Camera integration for photos
- ✅ Voice memo recording
- ✅ Auto-save every 30 seconds
- ✅ Progress tracking
- ✅ Offline capability (PWA ready)
- ✅ Real-time status updates

### API Endpoints Required:
- `upload-photo.php` - Handle photo uploads
- `voice-memo-upload.php` - Already exists (✅)
- `autosave.php` - Auto-save draft
- `save-draft.php` - Manual save
- `submit-report.php` - Submit completed report
- `get-draft.php` - Load existing draft

### Testing:
1. Access: https://staff.vapeshed.co.nz/modules/store-reports/views/mobile/create-report.php
2. Select outlet
3. Complete checklist items
4. Take photos / record voice memos
5. Save draft or submit

### Mobile Optimizations:
- Touch-friendly 44px+ tap targets
- No hover states (mobile doesn't hover)
- Large fonts (16px+)
- Fixed header/footer for easy access
- Camera uses native capture
- Voice recording uses Web Audio API

