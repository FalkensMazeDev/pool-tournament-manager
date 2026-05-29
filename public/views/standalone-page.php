<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
    <meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $page_title ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( PTM_PLUGIN_URL . 'public/css/public.css?v=' . PTM_VERSION ); ?>">
<?php $head_scripts = PTM_Settings::get( 'head_scripts' ); if ( $head_scripts ) echo $head_scripts . "\n"; ?>
</head>
<body class="ptm-standalone-page">

<?php echo $content_html; ?>

<script>
    var PTM = {
        restUrl:    <?php echo wp_json_encode( rest_url( 'gdc/v1/' ) ); ?>,
        pollInterval: <?php echo (int) PTM_Settings::get( 'poll_interval_ms' ); ?>,
        subPage:    <?php echo wp_json_encode( $sub_page ?? 'bracket' ); ?>,
        resultsUrl: <?php echo wp_json_encode( isset( $tournament ) ? PTM_Tournament::get_url( (array) $tournament, 'results' ) : '' ); ?>
    };
</script>
<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
<script src="<?php echo esc_url( PTM_PLUGIN_URL . 'public/js/public.js?v=' . PTM_VERSION ); ?>"></script>

<footer class="ptm-standalone-footer">
    &copy; <?php echo date( 'Y' ); ?> <a href="https://www.billiardgreg.com" target="_blank" rel="noopener noreferrer">Greg Whitehead</a>
</footer>

<?php $footer_scripts = PTM_Settings::get( 'footer_scripts' ); if ( $footer_scripts ) echo $footer_scripts . "\n"; ?>
</body>
</html>
