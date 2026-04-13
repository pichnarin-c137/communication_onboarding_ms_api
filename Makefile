.PHONY: up down restart build fresh shell logs \
        cache-clear redis-clear queue-restart migrate \
        frontend-logs frontend-shell bot-logs

#  Lifecycle
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build --no-cache

#  Database
migrate:
	docker compose exec app php artisan migrate --force

fresh:
	docker compose exec app php artisan migrate:fresh --seed --force

#  Cache Clearing
cache-clear:
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	@echo "Laravel cache cleared."

redis-clear:
	docker compose exec redis redis-cli FLUSHALL
	@echo "Redis flushed."

clear-all: cache-clear redis-clear

#  Queue
queue-restart:
	docker compose restart queue

#  Dev Helpers
shell:
	docker compose exec app bash

logs:
	docker compose logs -f

logs-app:
	docker compose logs -f app

logs-queue:
	docker compose logs -f queue

logs-frontend:
	docker compose logs -f frontend

frontend-shell:
	docker compose exec frontend sh

bot-logs:
	docker compose logs -f telegram-bot
