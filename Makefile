install:
	composer install
	npm install

setup:
	cp -n .env.example .env || true
	php artisan key:generate
	php artisan migrate --seed

build:
	npm run build

test:
	php artisan test

generate-key:
	php artisan arcflow:api-key 1 default

monitor:
	php artisan arcflow:monitor --once
