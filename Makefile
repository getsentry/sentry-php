.PHONY: test

develop:
	composer install --dev
	make setup-git

test:
	vendor/phpunit/phpunit/phpunit.php

setup-git:
	git config branch.autosetuprebase always
