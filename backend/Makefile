.PHONY: install serve test lint clean

install:
	composer install

serve:
	symfony server:start

test:
	./bin/phpunit

lint:
	./vendor/bin/php-cs-fixer fix --dry-run --diff 2>/dev/null || true
	./vendor/bin/phpstan analyse src 2>/dev/null || true

clean:
	rm -rf var/cache var/log vendor
