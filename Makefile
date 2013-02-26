.PHONY: test

develop:
	composer install --dev

test:
	vendor/bin/phpunit
