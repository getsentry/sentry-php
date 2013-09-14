.PHONY: test

develop:
	composer install --dev
	make setup-git

test:
	vendor/bin/phpunit

setup-git:
	git config branch.autosetuprebase always
