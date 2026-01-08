.PHONY: install test build demo clean

-include .env
export

install:
	cd backend && $(MAKE) install
	cd frontend && $(MAKE) install

test:
	cd backend && $(MAKE) test
	cd frontend && $(MAKE) test

build:
	cd frontend && $(MAKE) build
	cd backend && rm -rf var/cache

demo: build
	@echo "Starting demo at http://localhost:8000/demo.html"
	@echo ""
	@cp frontend/dist/pop.min.js backend/public/
	@cp frontend/dist/pop.min.css backend/public/
	@cp dist/*.html backend/public/
	@cd backend && APP_ENV=prod APP_SECRET=demo POP_ALLOWED_DOMAINS=http://localhost:8000 POP_DATABASE_PATH=var/data.db php -S localhost:8000 -t public &
	@sleep 1
	@open http://localhost:8000/demo.html || xdg-open http://localhost:8000/demo.html || echo "Open http://localhost:8000/demo.html"
	@echo "Press Ctrl+C to stop"
	@wait

clean:
	cd backend && $(MAKE) clean
	cd frontend && $(MAKE) clean
