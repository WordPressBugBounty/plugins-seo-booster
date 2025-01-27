<?php
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>SEO Booster PDF Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .report { padding: 20px; }
        .report h1 { color: #333; }
        /* Add any additional PDF-specific styles here */
    </style>
</head>
<body>
    <div class="report">
        <h1>SEO Booster PDF Report</h1>
        <?php if ($index_status_issues): ?>
            <?php echo wp_kses_post($index_status_issues); ?>
        <?php else: ?>
            <p>No index status issues found.</p>
        <?php endif; ?>

        <?php if ($keyword_canabalisations): ?>
            <?php echo wp_kses_post($keyword_canabalisations); ?>
        <?php else: ?>
            <p>No enhanced queries found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
return ob_get_clean();
?>
