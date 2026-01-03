.PHONY: install test build dist demo clean

install:
	cd backend && $(MAKE) install
	cd frontend && $(MAKE) install

test:
	cd backend && $(MAKE) test
	cd frontend && $(MAKE) test

build:
	cd frontend && $(MAKE) build
	cd backend && rm -rf var/cache

dist:
	./scripts/build.sh

demo: dist
	@echo "Starting demo at http://localhost:8000/demo.html"
	@echo ""
	@cd build && APP_ENV=prod APP_SECRET=demo POP_ALLOWED_DOMAINS=http://localhost:8000 POP_DATABASE_PATH=var/data.db php -S localhost:8000 -t public &
	@sleep 1
	@open http://localhost:8000/demo.html || xdg-open http://localhost:8000/demo.html || echo "Open http://localhost:8000/demo.html"
	@echo "Press Ctrl+C to stop"
	@wait

clean:
	cd backend && $(MAKE) clean
	cd frontend && $(MAKE) clean
	rm -rf build
