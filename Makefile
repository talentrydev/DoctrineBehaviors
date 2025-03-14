PHP_IMAGE=php:8.4-cli

deps:
	docker run -v $(shell pwd):/app --rm -t composer install --ignore-platform-reqs
test:
	docker run --rm -v $(shell pwd):/app -w /app -t $(PHP_IMAGE) vendor/bin/phpunit
