<?php
$documentRoot = '/files';
$pageCssFile = basename($pageCss);
$pageCssPath = __DIR__ . '/../css/' . $pageCssFile;
$pageCssVersion = file_exists($pageCssPath) ? filemtime($pageCssPath) : time();
?>
<!DOCTYPE html>
<html>

<head>
    <title>WP Plugins</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="cubes d.o.o">
    <meta name="description" content='WP Plugins'>
    <meta name="keywords" content="">
    <meta name="theme-color" content="#FF4B51">


    <!--ios compatibility-->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content='Opis sajta'>
    <link rel="apple-touch-icon" href="apple-icon-144x144.png">


    <!--Android compatibility-->

    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content='Opis sajta'>
    <link rel="icon" type="image/png" href="android-icon-192x192.png">


    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <!--CSS FILES-->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@300..800&family=Host+Grotesk:ital,wght@0,300..800;1,300..800&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">


    <link href="css/owl.carousel.css" rel="stylesheet" type="text/css" />
    <link href="css/<?php echo htmlspecialchars($pageCssFile, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $pageCssVersion; ?>"
        rel="stylesheet" type="text/css" />

</head>

<body>