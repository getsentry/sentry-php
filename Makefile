.PHONY: test

develop: update-submodules
	composer install --dev
	make setup-git

update-submodules:
	git submodule init
	git submodule update

cs:
	vendor/bin/php-cs-fixer fix --config=.php_cs --verbose --diff

cs-dry-run:
	vendor/bin/php-cs-fixer fix --config=.php_cs --verbose --diff --dry-run

cs-fix:
	vendor/bin/php-cs-fixer fix --config=.php_cs

phpstan:
	vendor/bin/phpstan analyse src tests -c phpstan.neon -l 3

test: cs-fix phpstan
	vendor/bin/phpunit

setup-git:
	git config branch.autosetuprebase always
