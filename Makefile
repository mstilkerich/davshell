.PHONY: stylecheck staticanalysis

all: stylecheck staticanalysis doc

stylecheck:
	phpcs.phar --colors --standard=PSR12 src/

staticanalysis:
	vendor/bin/psalm

doc:
	rm -r ~/www/davshell/*
	phpDocumentor.phar -d src/ -t ~/www/davshell --title="(Card)DAV Shell" 
