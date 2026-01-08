.PHONY: install dev test lint build clean

install:
	composer install
	npm install

dev:
	@npm run build
	@npm run watch & \
	symfony serve --port=8000 --no-tls

test:
	npm test
	./bin/phpunit

lint:
	./vendor/bin/php-cs-fixer fix --dry-run --diff 2>/dev/null || true
	./vendor/bin/phpstan analyse src 2>/dev/null || true

build:
	npm run build
	composer install --no-dev --no-scripts --optimize-autoloader --quiet

clean:
	rm -rf var/cache var/log vendor node_modules public/pop.min.js public/pop.min.css
