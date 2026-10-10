<?php

// Leitura sem Laravel, .env ou caches: também funciona antes do composer install.
$source = isset($argv[1]) ? file_get_contents($argv[1]) : stream_get_contents(STDIN);
if ($source === false) {
    fwrite(STDERR, "Não foi possível ler config/version.php.\n");
    exit(1);
}
// O formato antigo é aceito apenas para identificar checkouts anteriores/rollback.
if (! preg_match("/'current'\s*=>\s*'([0-9]+\.[0-9]+\.[0-9]+)'/", $source, $matches)
    && ! preg_match("/'current'\s*=>\s*env\(\s*'APP_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\)/", $source, $matches)) {
    fwrite(STDERR, "Versão do código não encontrada em config/version.php.\n");
    exit(1);
}
echo $matches[1];
