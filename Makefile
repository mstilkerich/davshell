.PHONY: all stylecheck phpcompatcheck staticanalyses psalmanalysis doc

all: stylecheck staticanalysis doc

staticanalyses: stylecheck phpcompatcheck psalmanalysis

stylecheck:
	vendor/bin/phpcs --colors --standard=PSR12 src/

phpcompatcheck:
	vendor/bin/phpcs --colors --standard=PHPCompatibility --runtime-set testVersion 7.1 src/

psalmanalysis:
	vendor/bin/psalm

doc:
	rm -r ~/www/davshell/*
	phpDocumentor.phar -d src/ -t ~/www/davshell --title="(Card)DAV Shell" 
