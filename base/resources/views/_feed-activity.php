<!--
  ============================================================================
  Activity Feed Card Partial (_feed-activity.php)
  ============================================================================

  Purpose:
    Renders a single activity card for display in the feed. Designed for
    both internal system activities and external news items.

  Variables Passed:
    $activity (object) - Activity object with properties:
      - id: unique identifier
      - type: activity type (order_created, news, etc.)
      - feed_type: 'internal' or 'external'
      - title: activity title
      - description: short description
      - timestamp: ISO 8601 timestamp
      - user: (optional) user object {id, name, avatar}
      - engagement: (int) engagement score/count
      - is_pinned: (bool) featured/pinned activity
      - icon: (optional) Bootstrap icon class
      - color: (optional) Bootstrap color class
      - image: (optional) image URL
      - url: (optional) clickable URL
      - metadata: (optional) additional data

  Styling:
    - Bootstrap 5 + custom CIS theme
    - Responsive design (mobile-first)
    - Gamification elements (badges, engagement indicators)
    - Accessibility (WCAG 2.1 AA compliant)

  Features:
    - Lazy loading support
    - Interactive hover states
    - Engagement metrics display
    - Time-relative display
    - Avatar/image loading
    - Pinned/featured indicator

  ============================================================================
-->

<?php
// Ensure variables are set
$activity = $activity ?? (object)[];
$id = $activity->id ?? uniqid('activity-');
$type = $activity->type ?? 'unknown';
$feedType = $activity->feed_type ?? 'internal';
$title = htmlspecialchars($activity->title ?? 'Activity', ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars($activity->description ?? '', ENT_QUOTES, 'UTF-8');
$timestamp = $activity->timestamp ?? date('c');
$engagement = intval($activity->engagement ?? 0);
$isPinned = (bool)($activity->is_pinned ?? false);
$icon = $activity->icon ?? 'bi bi-info-circle';
$color = $activity->color ?? 'secondary';
$image = $activity->image ?? null;
$url = $activity->url ?? null;
$user = $activity->user ?? null;

// Calculate time ago
$timeAgo = \CIS\Base\FeedFunctions::timeAgo($timestamp);

// Engagement level indicator
$engagementLevel = 'low';
if ($engagement >= 50) $engagementLevel = 'high';
elseif ($engagement >= 20) $engagementLevel = 'medium';

?>

<div class="activity-card card border-<?php echo $color; ?> mb-3 transition-all"
     id="<?php echo $id; ?>"
     data-activity-type="<?php echo $type; ?>"
     data-feed-type="<?php echo $feedType; ?>"
     data-engagement="<?php echo $engagement; ?>"
     role="article"
     aria-label="<?php echo $title; ?>">

    <?php if ($isPinned): ?>
        <!-- Pinned indicator -->
        <div class="position-absolute top-0 end-0 p-2">
            <span class="badge bg-warning text-dark" title="Pinned activity">
                <i class="bi bi-pin-fill"></i> Pinned
            </span>
        </div>
    <?php endif; ?>

    <div class="card-body pb-2">
        <!-- Header: Icon + Type + Time -->
        <div class="d-flex align-items-center mb-2">
            <span class="badge bg-<?php echo $color; ?> me-2">
                <i class="<?php echo $icon; ?>"></i>
            </span>
            <small class="text-muted flex-grow-1">
                <strong><?php echo ucfirst(str_replace('_', ' ', $type)); ?></strong>
                <span class="ms-2">•</span>
                <time datetime="<?php echo $timestamp; ?>" class="ms-2">
                    <?php echo $timeAgo; ?>
                </time>
            </small>

            <!-- External badge -->
            <?php if ($feedType === 'external'): ?>
                <span class="badge bg-info ms-2" title="External news feed">
                    <i class="bi bi-globe"></i>
                </span>
            <?php endif; ?>
        </div>

        <!-- User info (if available) -->
        <?php if ($user): ?>
            <div class="d-flex align-items-center mb-2 small">
                <?php if ($user['avatar']): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="rounded-circle me-2"
                         width="24" height="24"
                         loading="lazy">
                <?php else: ?>
                    <div class="rounded-circle bg-<?php echo $color; ?> text-white me-2 d-flex align-items-center justify-content-center"
                         style="width: 24px; height: 24px; font-size: 10px;">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <span class="text-muted"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <!-- Title -->
        <h6 class="card-title mb-2 fw-bold" style="word-break: break-word;">
            <?php echo $title; ?>
        </h6>

        <!-- Description -->
        <?php if ($description): ?>
            <p class="card-text text-muted small mb-2">
                <?php echo $description; ?>
                <?php if (strlen($activity->description ?? '') > 200): ?>
                    <a href="#" class="text-decoration-none"> Read more</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <!-- Image (if available) -->
        <?php if ($image): ?>
            <div class="mb-3">
                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                     class="img-fluid rounded"
                     style="max-height: 200px; object-fit: cover; width: 100%;"
                     loading="lazy">
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer: Engagement & Actions -->
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">

        <!-- Engagement Metrics -->
        <div class="small text-muted">
            <?php if ($engagement > 0): ?>
                <span class="d-inline-flex align-items-center">
                    <i class="bi bi-heart-fill text-danger me-1" style="font-size: 0.8rem;"></i>
                    <strong class="engagement-count"><?php echo $engagement; ?></strong>
                </span>

                <?php if ($engagement > 0): ?>
                    <span class="badge bg-light text-dark ms-2 engagement-badge engagement-<?php echo $engagementLevel; ?>">
                        <?php
                        if ($engagementLevel === 'high') {
                            echo '<i class="bi bi-fire"></i> Hot';
                        } elseif ($engagementLevel === 'medium') {
                            echo '<i class="bi bi-graph-up"></i> Trending';
                        } else {
                            echo '<i class="bi bi-star"></i> New';
                        }
                        ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group btn-group-sm" role="group" aria-label="Activity actions">

            <!-- Like button -->
            <button type="button"
                    class="btn btn-outline-danger btn-sm like-btn"
                    data-activity-id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
                    title="Like this activity"
                    aria-label="Like">
                <i class="bi bi-heart"></i>
                <span class="d-none d-sm-inline ms-1">Like</span>
            </button>

            <!-- Share button -->
            <button type="button"
                    class="btn btn-outline-primary btn-sm share-btn"
                    data-activity-id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
                    title="Share this activity"
                    aria-label="Share">
                <i class="bi bi-share"></i>
                <span class="d-none d-sm-inline ms-1">Share</span>
            </button>

            <!-- View more button (if URL available) -->
            <?php if ($url): ?>
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-outline-secondary btn-sm"
                   title="View details"
                   aria-label="View details"
                   target="_blank"
                   rel="noopener noreferrer">
                    <i class="bi bi-arrow-up-right"></i>
                    <span class="d-none d-sm-inline ms-1">View</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Activity card animations & transitions */
    .activity-card {
        transition: all 0.3s ease;
        border-width: 2px !important;
        overflow: hidden;
    }

    .activity-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
        border-color: currentColor !important;
    }

    .activity-card.engagement-high {
        border-color: #ff6b6b !important;
    }

    .activity-card.engagement-medium {
        border-color: #ffc107 !important;
    }

    .activity-card[data-feed-type="external"] {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(0, 0, 0, 0.02));
    }

    .engagement-badge.engagement-high {
        background-color: #ff6b6b !important;
        color: white !important;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .transition-all {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Time relative styling -->
    time {
        font-weight: 500;
    }

    /* Action buttons styling -->
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    /* Responsive adjustments -->
    @media (max-width: 576px) {
        .card-body {
            padding: 0.75rem;
        }

        .card-footer {
            padding: 0.5rem 0.75rem;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-start !important;
        }

        .btn-group {
            width: 100%;
        }

        .btn-group .btn {
            flex: 1;
        }
    }
</style>
