#!/bin/bash
###############################################################################
# SMART CRON V2 - FLAGGED PRODUCTS ACTIVATION
# Adds all 5 wrapped cron jobs to system crontab
###############################################################################

echo "=========================================================================="
echo " SMART CRON V2 - FLAGGED PRODUCTS CRON ACTIVATION"
echo "=========================================================================="
echo ""

# Backup current crontab
echo "📦 Backing up current crontab..."
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt
echo "✅ Backup saved to /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt"
echo ""

# Create new crontab entries
echo "📝 Creating flagged products cron entries..."
cat > /tmp/flagged_products_cron.txt << 'CRONEOF'

# ========================================================================
# FLAGGED PRODUCTS MODULE - Smart Cron V2 Wrapped Jobs
# Added: $(date +"%Y-%m-%d %H:%M:%S")
# ========================================================================

# Generate Daily Products - Daily 7:05 AM (CRITICAL)
5 7 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/generate_daily_products_wrapped.php >> /home/master/applications/jcepnzzkmj/logs/flagged_products_daily.log 2>&1

# Refresh Leaderboard - Daily 2:00 AM
0 2 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/refresh_leaderboard_wrapped.php >> /home/master/applications/jcepnzzkmj/logs/flagged_products_leaderboard.log 2>&1

# Generate AI Insights - Hourly
0 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/generate_ai_insights_wrapped.php >> /home/master/applications/jcepnzzkmj/logs/flagged_products_ai.log 2>&1

# Check Achievements - Every 6 hours
0 */6 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/check_achievements_wrapped.php >> /home/master/applications/jcepnzzkmj/logs/flagged_products_achievements.log 2>&1

# Refresh Store Stats - Every 30 minutes
*/30 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/refresh_store_stats_wrapped.php >> /home/master/applications/jcepnzzkmj/logs/flagged_products_stats.log 2>&1

CRONEOF

# Combine existing crontab with new entries
echo "🔗 Adding to crontab..."
crontab -l > /tmp/current_cron.txt
cat /tmp/flagged_products_cron.txt >> /tmp/current_cron.txt
crontab /tmp/current_cron.txt

echo "✅ Flagged products cron jobs added successfully!"
echo ""

# Display what was added
echo "📋 Added cron jobs:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat /tmp/flagged_products_cron.txt
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Verify
echo "✅ Current crontab entry count:"
crontab -l | grep -v "^#" | grep -v "^$" | wc -l
echo ""

echo "🎯 ACTIVATION COMPLETE!"
echo ""
echo "📊 Monitor execution:"
echo "   • Dashboard: https://staff.vapeshed.co.nz/assets/services/cron/dashboard.php"
echo "   • Logs: tail -f /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/logs/cron-\$(date +%Y-%m-%d).log"
echo "   • Metrics: mysql jcepnzzkmj -e \"SELECT * FROM flagged_products_cron_metrics ORDER BY created_at DESC LIMIT 10;\""
echo ""
echo "🔍 Next execution times:"
echo "   • Refresh Store Stats: Next :00 or :30"
echo "   • Generate AI Insights: Next hour (:00)"
echo "   • Check Achievements: Next 6-hour mark (00:00, 06:00, 12:00, 18:00)"
echo "   • Refresh Leaderboard: Tomorrow 02:00"
echo "   • Generate Daily Products: Tomorrow 07:05"
echo ""
echo "=========================================================================="
