FROM mcr.microsoft.com/devcontainers/php:8.3

RUN php -r 'if (version_compare(PHP_VERSION, "8.3.36", "<")) { fwrite(STDERR, "PHP 8.3.36 or newer is required.\n"); exit(1); }'

RUN ( curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - || echo 'return 1' ) | sh -s \
      intl