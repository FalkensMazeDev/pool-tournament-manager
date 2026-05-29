<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
    <meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $page_title ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( PTM_PLUGIN_URL . 'public/css/public.css?v=' . PTM_VERSION ); ?>">
</head>
<body class="ptm-standalone-page">

<?php echo $content_html; ?>

<script>
    var PTM = {
        restUrl: <?php echo wp_json_encode( rest_url( 'gdc/v1/' ) ); ?>,
        pollInterval: <?php echo (int) PTM_Settings::get( 'poll_interval_ms' ); ?>
    };
</script>
<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
<script src="<?php echo esc_url( PTM_PLUGIN_URL . 'public/js/public.js?v=' . PTM_VERSION ); ?>"></script>

</body>
</html>
