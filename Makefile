# Makefile for linux

VERSION = "3.1.4-Beta4"
VERSION2 = $(shell echo $(VERSION)|sed 's/ /-/g')
ZIPFILE = plg_attachments_for_simplecalendar_$(VERSION2).zip

FILES = *.php *.xml language/*/*

all: $(ZIPFILE)

ZIPIGNORES = -x "*.zip" -x "*~" -x "*.git/*" -x "*.gitignore" -x Makefile

$(ZIPFILE): $(FILES)
	@echo "-------------------------------------------------------"
	@echo "Creating plugin zip file: $(ZIPFILE)"
	@echo ""
	@zip -r $@ * $(ZIPIGNORES)
	@echo "-------------------------------------------------------"
	@echo "done"
