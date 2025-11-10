<!-- Footer Component -->
<footer class="cis-footer">
    <div class="footer-content">
        <div class="footer-left">
            <p class="footer-text">
                &copy; <?= date('Y') ?> <strong>Ecigdis Limited</strong> | The Vape Shed
            </p>
            <p class="footer-subtext">
                Central Information System (CIS) v2.0.0
            </p>
        </div>
        
        <div class="footer-center">
            <ul class="footer-links">
                <li><a href="/help.php">Help</a></li>
                <li><a href="/documentation.php">Documentation</a></li>
                <li><a href="/support.php">Support</a></li>
                <li><a href="/privacy.php">Privacy Policy</a></li>
                <li><a href="/terms.php">Terms of Service</a></li>
            </ul>
        </div>
        
        <div class="footer-right">
            <p class="footer-text">
                <i class="fas fa-server"></i> Server: <?= gethostname() ?>
            </p>
            <p class="footer-subtext">
                <i class="fas fa-clock"></i> <?= date('d M Y H:i:s') ?>
            </p>
        </div>
    </div>
</footer>

<style>
    .cis-footer {
        background-color: var(--cis-gray-100);
        border-top: 1px solid var(--cis-border-color);
        padding: 1.5rem;
        margin-top: auto;
    }
    
    .footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1400px;
        margin: 0 auto;
        gap: 2rem;
    }
    
    .footer-left,
    .footer-center,
    .footer-right {
        flex: 1;
    }
    
    .footer-center {
        text-align: center;
    }
    
    .footer-right {
        text-align: right;
    }
    
    .footer-text {
        margin: 0 0 0.25rem 0;
        font-size: var(--cis-font-size-sm);
        color: var(--cis-gray-700);
    }
    
    .footer-subtext {
        margin: 0;
        font-size: var(--cis-font-size-xs);
        color: var(--cis-gray-600);
    }
    
    .footer-links {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    
    .footer-links a {
        color: var(--cis-gray-600);
        text-decoration: none;
        font-size: var(--cis-font-size-sm);
        transition: color 0.2s;
    }
    
    .footer-links a:hover {
        color: var(--cis-primary);
    }
    
    .footer-right i {
        margin-right: 0.25rem;
        color: var(--cis-gray-500);
    }
    
    @media (max-width: 768px) {
        .footer-content {
            flex-direction: column;
            text-align: center;
        }
        
        .footer-right {
            text-align: center;
        }
        
        .footer-links {
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>
