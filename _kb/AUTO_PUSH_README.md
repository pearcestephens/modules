# 🚀 Auto-Push Monitor Setup

## What It Does

✅ **Automatically pushes to GitHub every 5 minutes**
✅ **Only when you're actively working** (detects file changes)
✅ **Batches multiple changes** into single commits
✅ **Runs silently in background**
✅ **Auto-restarts if it crashes**

## Current Status

```bash
php .auto-push-monitor.php status
```

Expected output:
```
Status: 🟢 RUNNING
Pushes to GitHub every 5 minutes when changes detected
```

---

## Control Commands

### Start Monitoring
```bash
php .auto-push-monitor.php start
```
- Starts the monitor in background
- Auto-pushes every 5 minutes
- Only when changes detected

### Stop Monitoring
```bash
php .auto-push-monitor.php stop
```
- Stops the auto-push daemon
- Manual `git push` still works

### Check Status
```bash
php .auto-push-monitor.php status
```
- Shows if monitor is running
- Displays recent log entries
- Confirms last activity

### View Live Log
```bash
tail -f .auto-push.log
```
- Watch real-time push activity
- See all commit messages
- Debug any failures

---

## How It Works (Under the Hood)

### Every 5 Minutes:
1. **Detects changes** → `git status --porcelain`
2. **Stages all files** → `git add .`
3. **Creates commit** → `git commit -m "Auto-push: timestamp (N files)"`
4. **Pushes to GitHub** → `git push origin main`
5. **Logs everything** → `.auto-push.log`

### Smart Features:
- ✅ **Only pushes if changes exist** (no empty commits)
- ✅ **Batches changes** (waits 5 min for more edits)
- ✅ **Handles failures** (retries once on push failure)
- ✅ **Detects idle time** (skips logs if no activity)
- ✅ **Clean logging** (timestamps, file counts)

---

## Auto-Start Options

### Option 1: Manual (Every Session)
```bash
# In terminal, run:
php .auto-push-monitor.php start

# Or use the startup script:
bash start-auto-push.sh
```

### Option 2: Add to Shell Profile
```bash
# Edit ~/.bashrc or ~/.bash_profile:
# Add this line:
[ -d "/home/master/applications/jcepnzzkmj/public_html/modules" ] && \
  php /home/master/applications/jcepnzzkmj/public_html/modules/.auto-push-monitor.php start 2>/dev/null &

# Then reload:
source ~/.bashrc
```

### Option 3: VSCode Task (Recommended!)
Create `.vscode/tasks.json` in the modules folder:
```json
{
  "version": "2.0.0",
  "tasks": [
    {
      "label": "Start Auto-Push Monitor",
      "type": "shell",
      "command": "php",
      "args": [".auto-push-monitor.php", "start"],
      "presentation": {
        "echo": true,
        "reveal": "silent"
      },
      "runOptions": {
        "runOn": "folderOpen"
      }
    }
  ]
}
```

---

## Example Workflow

### Scenario: Making Changes
```
1. Edit files in VS Code
2. Save changes (Ctrl+S)
3. Monitor detects changes automatically
4. Waits 5 minutes for more edits
5. Automatically:
   - git add .
   - git commit -m "Auto-push: 2025-10-31 16:15:42 (12 files)"
   - git push origin main
6. You see in GitHub: new commit with all changes
```

### Scenario: Multiple Edits in 5 Minutes
```
1. Edit file-1.php (1:00 PM)
2. Edit file-2.php (1:02 PM)
3. Edit file-3.php (1:04 PM)
4. At 1:05 PM → Single commit with all 3 files
   (not 3 separate commits)
```

### Scenario: No Changes
```
1. Auto-push checks
2. No changes detected
3. Skips this cycle
4. Tries again in 5 minutes
```

---

## Log File Analysis

### Real-Time Monitoring
```bash
tail -f .auto-push.log
```

### Sample Log Output
```
[2025-10-31 16:13:09] Auto-push daemon started (PID: 21344)
[2025-10-31 16:18:15] Detected 5 changed files
[2025-10-31 16:18:15] Created commit: Auto-push: 2025-10-31 16:18:15 (5 files)
[2025-10-31 16:18:18] ✓ Pushed to GitHub (via origin/main)
[2025-10-31 16:23:32] Detected 8 changed files
[2025-10-31 16:23:32] Created commit: Auto-push: 2025-10-31 16:23:32 (8 files)
[2025-10-31 16:23:45] ✓ Pushed to GitHub (via origin/main)
[2025-10-31 16:28:50] No changes to push
```

---

## Troubleshooting

### Monitor Not Running?
```bash
# Check status
php .auto-push-monitor.php status

# Start it
php .auto-push-monitor.php start

# Check log
tail -20 .auto-push.log
```

### Push Failures?
```bash
# Check git status
git status

# Check remote
git remote -v

# Test manual push
git push origin main

# View detailed log
tail -100 .auto-push.log | grep -i "failed\|error"
```

### Too Many Commits?
```bash
# Adjust check interval in .auto-push-monitor.php
# Line: 'check_interval' => 300,  (300 = 5 minutes)
# Change to 600 for 10 minutes, 900 for 15 minutes, etc.
```

### Monitor Crashing?
```bash
# Check for errors
tail -50 .auto-push.log

# Restart it
php .auto-push-monitor.php stop
sleep 2
php .auto-push-monitor.php start

# View status again
php .auto-push-monitor.php status
```

---

## GitHub Integration

### What You'll See on GitHub
```
Commit: "Auto-push: 2025-10-31 16:18:15 (5 files)"
Branch: main
Files Changed: 5
Size: ~50KB average
Frequency: Every 5 minutes (when active)
```

### Your Commit History
- Clean, organized commits
- Timestamps show exactly when pushed
- File counts for reference
- Regular intervals (not chaotic)

### Backup Benefits
✅ Multiple commits = better version history
✅ No lost work (auto-pushes every 5 min)
✅ Easy to revert specific changes
✅ GitHub keeps 30-day history anyway

---

## Quick Reference

| Command | Purpose |
|---------|---------|
| `php .auto-push-monitor.php start` | Start auto-push |
| `php .auto-push-monitor.php stop` | Stop auto-push |
| `php .auto-push-monitor.php status` | Check status |
| `tail -f .auto-push.log` | Live log |
| `git push origin main` | Manual push (override) |
| `git log --oneline` | View commit history |

---

## Summary

✅ **Auto-push is now RUNNING**
✅ **Pushes every 5 minutes automatically**
✅ **Only when you're working (smart detection)**
✅ **Batches changes into single commits**
✅ **Never loses work**
✅ **Logs everything for debugging**

**Start using it:** Just keep working! Changes auto-push every 5 minutes. 🚀

---

**Questions?**
- Run: `php .auto-push-monitor.php status`
- Check log: `tail .auto-push.log`
- Manual push: `git push origin main`
