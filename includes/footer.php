<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (for AJAX) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo ASSETS_URL; ?>js/app.js"></script>

<!-- Page-specific JavaScript -->
<?php
// Include page-specific JavaScript if exists
$js_file = str_replace('.php', '.js', basename($_SERVER['PHP_SELF']));
$js_path = ASSETS_URL . 'js/' . $js_file;
if (file_exists(BASE_PATH . 'assets/js/' . $js_file)) {
    echo '<script src="' . $js_path . '"></script>';
}
?>

<!-- Toast notifications container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer">
    <!-- Toasts will be dynamically added here -->
</div>

<!-- Flash messages -->
<?php
$flash_messages = getFlashMessages();
foreach ($flash_messages as $type => $message):
?>
<script>
    showToast('<?php echo $type; ?>', '<?php echo addslashes($message); ?>');
</script>
<?php endforeach; ?>

</body>
</html>