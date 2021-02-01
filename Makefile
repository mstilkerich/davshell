DOCDIR := doc/api/
.PHONY: all stylecheck phpcompatcheck staticanalyses psalmanalysis doc

all: staticanalyses doc

staticanalyses: stylecheck phpcompatcheck psalmanalysis

stylecheck:
	vendor/bin/phpcs --colors --standard=PSR12 src/

phpcompatcheck:
	vendor/bin/phpcs --colors --standard=PHPCompatibility --runtime-set testVersion 7.1 src/

psalmanalysis:
	vendor/bin/psalm --no-cache --shepherd --report=testreports/psalm.txt --report-show-info=true --no-progress

doc:
	rm -rf $(DOCDIR)
	phpDocumentor.phar -d src/ -t $(DOCDIR) --title="(Card)DAV Shell"
