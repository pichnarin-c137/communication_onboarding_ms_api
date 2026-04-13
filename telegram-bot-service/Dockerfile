FROM php:8.4-cli-alpine

# curl extension for Telegram API + internal backend calls
RUN apk add --no-cache curl-dev \
    && docker-php-ext-install curl

WORKDIR /app
COPY telegram-bot-service/poll.php .

CMD ["php", "-f", "poll.php"]
