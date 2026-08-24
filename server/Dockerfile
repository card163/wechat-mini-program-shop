FROM php:8.4-cli-bookworm

# use aliyun mirror instead of deb.debian.org to avoid slow/stalled downloads from mainland China
RUN if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
        sed -i 's@deb.debian.org@mirrors.aliyun.com@g; s@security.debian.org@mirrors.aliyun.com/debian-security@g' /etc/apt/sources.list.d/debian.sources; \
    elif [ -f /etc/apt/sources.list ]; then \
        sed -i 's@deb.debian.org@mirrors.aliyun.com@g; s@security.debian.org@mirrors.aliyun.com/debian-security@g' /etc/apt/sources.list; \
    fi

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN docker-php-source extract

# install extensions
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_mysql pcntl \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# enable opcache and pcntl
RUN docker-php-ext-enable opcache pcntl
RUN docker-php-source delete && \
    rm -rf /var/lib/apt/lists/*

RUN mkdir -p /app
WORKDIR /app