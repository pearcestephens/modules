<?php
/**
 * User Profile Page
 * Display and edit user profile information
 */

require_once __DIR__ . '/../../base/templates/vape-ultra/config.php';
require_once __DIR__ . '/../../base/templates/vape-ultra/layouts/main.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controllers/ProfileController.php';

// Check authentication - Use CIS standard session variable: user_id (snake_case)
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Initialize controller
$controller = new ProfileController();

// Handle form submission
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'first_name' => htmlspecialchars($_POST['first_name'] ?? ''),
            'last_name' => htmlspecialchars($_POST['last_name'] ?? ''),
            'phone' => htmlspecialchars($_POST['phone'] ?? ''),
            'street_address' => htmlspecialchars($_POST['street_address'] ?? ''),
            'city' => htmlspecialchars($_POST['city'] ?? ''),
            'state' => htmlspecialchars($_POST['state'] ?? ''),
            'postal_code' => htmlspecialchars($_POST['postal_code'] ?? ''),
            'country' => htmlspecialchars($_POST['country'] ?? ''),
            'bio' => htmlspecialchars($_POST['bio'] ?? ''),
            'twitter_url' => htmlspecialchars($_POST['twitter_url'] ?? ''),
            'linkedin_url' => htmlspecialchars($_POST['linkedin_url'] ?? ''),
            'website_url' => htmlspecialchars($_POST['website_url'] ?? '')
        ];

        $result = $controller->updateProfile($_SESSION['user_id'], $data);
        if ($result) {
            $message = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    try {
        $result = $controller->uploadAvatar($_SESSION['user_id'], $_FILES['avatar']);
        if ($result) {
            $message = 'Avatar uploaded successfully!';
        } else {
            $error = 'Failed to upload avatar. File may be too large or invalid format.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get profile data
$profile = $controller->getProfile($_SESSION['user_id']);
$user = $_SESSION['user'] ?? [];

// Calculate profile completion
$completion = $controller->calculateCompletion($profile);

// Page configuration
$page = [
    'title' => 'My Profile',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Settings' => '/core/settings/',
        'Profile' => null
    ],
    'icon' => 'fas fa-user'
];

// Build content
$content = <<<HTML
<div class="profile-container">
    <div class="row">
        <!-- Left Column - Avatar & Quick Info -->
        <div class="col-md-4">
            <div class="card sticky-top">
                <div class="card-body text-center">
                    <!-- Avatar -->
                    <div class="avatar-container mb-3">
                        <img src="{$profile['avatar_url'] ?? '/images/default-avatar.png'}"
                             alt="Profile Avatar" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                    </div>

                    <!-- Name -->
                    <h4 class="card-title">{$profile['first_name']} {$profile['last_name']}</h4>
                    <p class="text-muted">{$user['email'] ?? ''}</p>

                    <!-- Role Badge -->
                    <div class="mb-3">
                        <span class="badge bg-primary">{$user['role'] ?? 'User'}</span>
                    </div>

                    <!-- Availability Status Display (Chat) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span id="statusBadge" class="badge bg-success">
                                <i class="fas fa-circle"></i> {ucfirst(str_replace('_', ' ', $profile['availability_status'] ?? 'online'))}
                            </span>
                            {!empty($profile['status_message']) ? '<small class="text-muted">"' . htmlspecialchars($profile['status_message']) . '"</small>' : ''}
                        </div>
                    </div>

                    <!-- Profile Completion -->
                    <div class="mb-3">
                        <div class="text-start mb-2">
                            <small class="text-muted">Profile Completion</small>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar"
                                     style="width: {$completion}%;"
                                     aria-valuenow="{$completion}" aria-valuemin="0" aria-valuemax="100">
                                    {$completion}%
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Member Since -->
                    <div class="text-start">
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i>
                            Member since {$profile['created_at'] ? date('M Y', strtotime($profile['created_at'])) : 'N/A'}
                        </small>
                    </div>

                    <!-- Links -->
                    <div class="mt-4 d-grid gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="fas fa-camera"></i> Change Avatar
                        </button>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#privacyModal">
                            <i class="fas fa-shield-alt"></i> Privacy & Blocking
                        </button>
                        <a href="/core/settings/" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Form -->
        <div class="col-md-8">
            <!-- Messages -->
            HTML;

if ($message) {
    $content .= <<<HTML
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> {$message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            HTML;
}

if ($error) {
    $content .= <<<HTML
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> {$error}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            HTML;
}

$content .= <<<HTML

            <!-- Profile Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <!-- Username & Display Options -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="mb-3 text-muted"><i class="fas fa-at"></i> Chat Identity</h6>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username/Handle <span class="badge bg-info">Chat</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="{$profile['username'] ?? ''}" placeholder="your_handle"
                                           pattern="^[a-zA-Z0-9_]{3,20}$" title="3-20 characters, letters/numbers/underscore only">
                                </div>
                                <small class="form-text text-muted">Unique identifier for messaging and mentions (3-20 characters)</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="displayName" class="form-label">Display Name (Optional)</label>
                                <input type="text" class="form-control" id="displayName" name="display_name"
                                       value="{$profile['display_name'] ?? ''}" placeholder="How you appear to others">
                                <small class="form-text text-muted">Shows instead of real name if set</small>
                            </div>
                        </div>

                        <!-- Availability & Status -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="mb-3 text-muted"><i class="fas fa-circle"></i> Availability & Status</h6>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="availability" class="form-label">Status <span class="badge bg-success">Chat</span></label>
                                <select class="form-select" id="availability" name="availability_status">
                                    <option value="online" {($profile['availability_status'] === 'online' ? 'selected' : '')}>
                                        <span class="badge bg-success">●</span> Online
                                    </option>
                                    <option value="away" {($profile['availability_status'] === 'away' ? 'selected' : '')}>
                                        <span class="badge bg-warning">●</span> Away
                                    </option>
                                    <option value="offline" {($profile['availability_status'] === 'offline' ? 'selected' : '')}>
                                        <span class="badge bg-secondary">●</span> Offline
                                    </option>
                                    <option value="do_not_disturb" {($profile['availability_status'] === 'do_not_disturb' ? 'selected' : '')}>
                                        <span class="badge bg-danger">●</span> Do Not Disturb
                                    </option>
                                </select>
                                <small class="form-text text-muted">Let others know your availability</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="statusMessage" class="form-label">Status Message</label>
                                <input type="text" class="form-control" id="statusMessage" name="status_message"
                                       value="{$profile['status_message'] ?? ''}" placeholder="e.g., At lunch, back soon!"
                                       maxlength="100">
                                <small class="form-text text-muted">Visible to other users (max 100 characters)</small>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="mb-3 text-muted"><i class="fas fa-id-card"></i> Personal Information</h6>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="first_name"
                                       value="{$profile['first_name'] ?? ''}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="last_name"
                                       value="{$profile['last_name'] ?? ''}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="{$profile['phone'] ?? ''}" placeholder="+1 (555) 000-0000">
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3">Address</h6>

                        <div class="mb-3">
                            <label for="address" class="form-label">Street Address</label>
                            <input type="text" class="form-control" id="address" name="street_address"
                                   value="{$profile['street_address'] ?? ''}" placeholder="123 Main St">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="{$profile['city'] ?? ''}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="state" name="state"
                                       value="{$profile['state'] ?? ''}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="postal" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal" name="postal_code"
                                       value="{$profile['postal_code'] ?? ''}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country"
                                   value="{$profile['country'] ?? ''}">
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3">Professional Information</h6>

                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"
                                      placeholder="Tell us about yourself...">{$profile['bio'] ?? ''}</textarea>
                            <small class="form-text text-muted">Maximum 500 characters</small>
                        </div>

                        <!-- Bio Visibility (Chat) -->
                        <div class="mb-3">
                            <label for="bioVisibility" class="form-label"><i class="fas fa-eye"></i> Bio Visibility</label>
                            <select class="form-select" id="bioVisibility" name="bio_visibility">
                                <option value="public" {($profile['bio_visibility'] === 'public' ? 'selected' : '')}>
                                    <i class="fas fa-globe"></i> Public - Everyone can see
                                </option>
                                <option value="contacts" {($profile['bio_visibility'] === 'contacts' ? 'selected' : '')}>
                                    <i class="fas fa-user-friends"></i> Contacts Only
                                </option>
                                <option value="private" {($profile['bio_visibility'] === 'private' ? 'selected' : '')}>
                                    <i class="fas fa-lock"></i> Private - Only me
                                </option>
                            </select>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3"><i class="fas fa-shield-alt"></i> Privacy Controls</h6>

                        <!-- Last Seen Privacy (Chat) -->
                        <div class="mb-3">
                            <label for="lastSeenPrivacy" class="form-label">Last Seen Visibility</label>
                            <select class="form-select" id="lastSeenPrivacy" name="last_seen_privacy">
                                <option value="everyone" {($profile['last_seen_privacy'] === 'everyone' ? 'selected' : '')}>
                                    <i class="fas fa-eye"></i> Everyone can see
                                </option>
                                <option value="contacts" {($profile['last_seen_privacy'] === 'contacts' ? 'selected' : '')}>
                                    <i class="fas fa-user-friends"></i> Contacts only
                                </option>
                                <option value="private" {($profile['last_seen_privacy'] === 'private' ? 'selected' : '')}>
                                    <i class="fas fa-lock"></i> Hidden from everyone
                                </option>
                            </select>
                            <small class="form-text text-muted">Controls who can see when you were last active</small>
                        </div>

                        <!-- Profile Picture Visibility (Chat) -->
                        <div class="mb-3">
                            <label for="avatarVisibility" class="form-label">Profile Picture Visibility</label>
                            <select class="form-select" id="avatarVisibility" name="avatar_visibility">
                                <option value="public" {($profile['avatar_visibility'] === 'public' ? 'selected' : '')}>
                                    <i class="fas fa-globe"></i> Public
                                </option>
                                <option value="contacts" {($profile['avatar_visibility'] === 'contacts' ? 'selected' : '')}>
                                    <i class="fas fa-user-friends"></i> Contacts only
                                </option>
                                <option value="private" {($profile['avatar_visibility'] === 'private' ? 'selected' : '')}>
                                    <i class="fas fa-lock"></i> Private
                                </option>
                            </select>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3">Social Links</h6>

                        <div class="mb-3">
                            <label for="twitter" class="form-label">
                                <i class="fab fa-twitter"></i> Twitter URL
                            </label>
                            <input type="url" class="form-control" id="twitter" name="twitter_url"
                                   value="{$profile['twitter_url'] ?? ''}" placeholder="https://twitter.com/yourhandle">
                        </div>

                        <div class="mb-3">
                            <label for="linkedin" class="form-label">
                                <i class="fab fa-linkedin"></i> LinkedIn URL
                            </label>
                            <input type="url" class="form-control" id="linkedin" name="linkedin_url"
                                   value="{$profile['linkedin_url'] ?? ''}" placeholder="https://linkedin.com/in/yourprofile">
                        </div>

                        <div class="mb-3">
                            <label for="website" class="form-label">
                                <i class="fas fa-globe"></i> Website URL
                            </label>
                            <input type="url" class="form-control" id="website" name="website_url"
                                   value="{$profile['website_url'] ?? ''}" placeholder="https://yourwebsite.com">
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>

                    <!-- Chat Privacy Actions -->
                    <div class="mt-4 pt-4 border-top">
                        <h6 class="mb-3"><i class="fas fa-comments"></i> Chat & Messaging</h6>
                        <div class="alert alert-info small mb-0">
                            <p class="mb-2"><i class="fas fa-info-circle"></i> <strong>Privacy Note:</strong> These settings affect who can message you and how they interact with your profile.</p>
                            <ul class="mb-0 ms-3">
                                <li>Block users to prevent them from messaging you</li>
                                <li>Report inappropriate users to our support team</li>
                                <li>Your availability and status help others know when you're available</li>
                                <li>Privacy controls are applied in real-time</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Privacy & Blocking Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt"></i> Privacy & Blocking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Blocked Users Section -->
                <div class="mb-4">
                    <h6><i class="fas fa-ban"></i> Blocked Users <span class="badge bg-danger">0</span></h6>
                    <p class="text-muted small">Users you've blocked cannot message you or see your online status</p>
                    <div class="list-group list-group-sm" id="blockedUsersList">
                        <p class="text-muted text-center py-3">No blocked users</p>
                    </div>
                </div>

                <!-- Reported Users Section -->
                <div class="mb-4">
                    <h6><i class="fas fa-flag"></i> Reported Users <span class="badge bg-warning">0</span></h6>
                    <p class="text-muted small">Users you've reported to our support team</p>
                    <div class="list-group list-group-sm" id="reportedUsersList">
                        <p class="text-muted text-center py-3">No reported users</p>
                    </div>
                </div>

                <div class="alert alert-warning small">
                    <i class="fas fa-exclamation-triangle"></i> Blocking and reporting are serious actions. We take user safety seriously.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="avatarInput" class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="avatarInput" name="avatar"
                               accept="image/*" required>
                        <small class="form-text text-muted">
                            Max 5MB. Supported: JPG, PNG, GIF, WebP
                        </small>
                    </div>
                    <div id="avatarPreview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .profile-container {
        padding: 20px 0;
    }

    .avatar-container img {
        border: 4px solid #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .card {
        border: 1px solid #eee;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }

    .card-title {
        color: white;
    }

    @media (max-width: 768px) {
        .sticky-top {
            position: relative !important;
        }
    }
</style>

<script>
// Avatar preview
document.getElementById('avatarInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview" class="img-fluid rounded">';
        };
        reader.readAsDataURL(file);
    }
});

// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const username = document.getElementById('username').value.trim();

    if (!firstName || !lastName) {
        e.preventDefault();
        alert('First and last name are required.');
        return;
    }

    if (!username) {
        e.preventDefault();
        alert('Username is required for chat messaging.');
        return;
    }

    // Validate username format
    const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
    if (!usernameRegex.test(username)) {
        e.preventDefault();
        alert('Username must be 3-20 characters, letters/numbers/underscore only.');
        return;
    }
});

// Update status badge color based on availability
document.getElementById('availability').addEventListener('change', function() {
    const badge = document.getElementById('statusBadge');
    const value = this.value;
    const colors = {
        'online': 'bg-success',
        'away': 'bg-warning',
        'offline': 'bg-secondary',
        'do_not_disturb': 'bg-danger'
    };
    const icons = {
        'online': 'fa-circle',
        'away': 'fa-circle',
        'offline': 'fa-circle',
        'do_not_disturb': 'fa-circle'
    };

    // Remove all color classes
    Object.values(colors).forEach(color => badge.classList.remove(color));
    // Add new color
    badge.classList.add(colors[value]);
    // Update text
    badge.innerHTML = '<i class="fas ' + icons[value] + '"></i> ' + value.charAt(0).toUpperCase() + value.slice(1).replace('_', ' ');
});

// Character counter for status message
document.getElementById('statusMessage').addEventListener('input', function() {
    const maxLength = 100;
    const length = this.value.length;
    if (length > maxLength) {
        this.value = this.value.substring(0, maxLength);
    }
});
</script>
HTML;

// Render with template
renderMainLayout($page, $content);
?>
