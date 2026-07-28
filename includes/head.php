<head>
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title><?php echo htmlspecialchars($appConfig['app_name'] ?? 'CSNSA - Gestor de Assiduidade', ENT_QUOTES, 'UTF-8'); ?></title>
  <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
  <link rel="icon" href="<?php echo htmlspecialchars($appConfig['favicon'] ?? 'assets/img/csnsa/favicon-nsa.png', ENT_QUOTES, 'UTF-8'); ?>" type="image/png" />

  <!-- Fonts and icons -->
  <script src="assets/js/plugin/webfont/webfont.min.js"></script>
  <script>
    WebFont.load({
      google: { families: ["Public Sans:300,400,500,600,700"] },
      custom: {
        families: [
          "Font Awesome 5 Solid",
          "Font Awesome 5 Regular",
          "Font Awesome 5 Brands",
          "simple-line-icons",
        ],
        urls: ["assets/css/fonts.min.css"],
      },
      active: function () {
        sessionStorage.fonts = true;
      },
    });
  </script>

  <!-- CSS Files -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/plugins.min.css" />
  <link rel="stylesheet" href="assets/css/kaiadmin.min.css" />

  <?php if (!empty($headExtraStyle)): ?>
    <style>
      <?php echo $headExtraStyle; ?>
    </style>
  <?php endif; ?>
</head>
