<?php
/**
 * Footer Template
 */
?>
    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            <div class="footer-links">
                <a href="<?php echo SITE_URL; ?>about.php">About</a>
                <a href="<?php echo SITE_URL; ?>contact.php">Contact</a>
                <a href="#">Privacy Policy</a>
            </div>
        </div>
    </footer>

    <!-- JS Files -->
    <script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>
    <script src="<?php echo SITE_URL; ?>assets/js/utils.js"></script>
    <?php if (isset($is_dashboard_view) && $is_dashboard_view): ?>
        <script src="<?php echo SITE_URL; ?>assets/js/pages/dashboard.js"></script>
    <?php endif; ?>
</body>
</html>
