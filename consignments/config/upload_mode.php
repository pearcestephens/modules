<?php
/**
 * Consignment Upload Mode Configuration
 * 
 * Toggle between queue-based and direct upload modes
 * 
 * MODES:
 * - 'direct'  = Direct upload (no queue, no workers needed) - FAST & RELIABLE
 * - 'queue'   = Queue-based upload (requires workers) - FOR BACKGROUND PROCESSING
 * 
 * DEFAULT: 'direct' (because workers keep dying every day)
 * 
 * @package CIS\Consignments\Config
 */

return [
    /**
     * Upload Mode
     * 
     * 'direct' - Uploads happen immediately when you click submit
     *            - No queue workers needed
     *            - Faster response
     *            - Better error visibility
     *            - USE THIS WHEN WORKERS ARE DEAD (every fucking day)
     * 
     * 'queue'  - Creates queue job for background workers
     *            - Requires workers to be running
     *            - Better for high-volume operations
     *            - USE THIS WHEN WORKERS ARE ACTUALLY WORKING (rare)
     */
    'mode' => 'direct', // Change to 'queue' when workers are fixed
    
    /**
     * Upload Display Mode
     * 
     * 'modal'  - Shows upload progress in a modal overlay (smaller, inline)
     * 'popup'  - Opens upload progress in a new window (original behavior)
     */
    'display' => 'modal', // Change to 'popup' if you prefer the old way
    
    /**
     * Gangsta Mode Expiry
     * 
     * After this date, the modal will go back to boring professional mode
     * Set to 2 weeks from October 16, 2025 = October 30, 2025
     */
    'gangsta_mode_expiry' => '2025-10-30 23:59:59',
    
    /**
     * Modal Personality Levels (based on time remaining until expiry)
     * 
     * Days Left | Style
     * ----------|-------
     * 14+ days  | FULL GANGSTA 🔥💯 (swearing, emojis, slang)
     * 7-13 days | HOOD PROFESSIONAL 😎 (cool but polite)
     * 3-6 days  | CORPORATE FRIENDLY 👔 (professional with personality)
     * 0-2 days  | BORING AF 💤 (generic corporate speak)
     */
    'personality_mode' => 'auto', // 'auto' = based on expiry, or force: 'gangsta', 'hood', 'corporate', 'boring'
    
    /**
     * Queue Job Type (only used if mode = 'queue')
     */
    'queue_job_type' => 'transfer.create_consignment',
    
    /**
     * Queue Priority (1-10, 10 = highest)
     */
    'queue_priority' => 8,
    
    /**
     * Direct Upload Settings
     */
    'direct_upload' => [
        'show_real_time_progress' => true,  // SSE progress updates
        'auto_close_on_success' => false,   // Keep modal open to see results
        'modal_width' => '900px',           // Modal size
        'modal_height' => '600px',
    ],
    
    /**
     * Debugging
     */
    'debug_mode' => true, // Log which mode is being used
    'log_path' => '/logs/consignment-upload.log',
];
