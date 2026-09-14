### Task 2: Docker dev environment

**Files:**
- Create: `docker/php/Dockerfile`, `docker/nginx/default.conf`, `docker-compose.yml`
- Test: `docker compose config`, `pg_isready` via exec

**Interfaces:**
- Consumes: Task 1 (repo).
- Produces: service `db` sehat (`db:5432`, user/pass `webvolunteer/secret`, db `webvolunteer`); service `app` dengan PHP 8.4 + composer (belum ada kode Laravel — itu Task 3).

- [ ] **Step 1: Tulis Dockerfile PHP**

`docker/php/Dockerfile`:

```dockerfile
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev libicu-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

- [ ] **Step 2: Tulis vhost Nginx**

`docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

- [ ] **Step 3: Tulis docker-compose.yml**

`docker-compose.yml`:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: webvolunteer-app
    volumes:
      - ./:/var/www/html
    environment:
      DB_HOST: db
      DB_DATABASE: webvolunteer
      DB_USERNAME: webvolunteer
      DB_PASSWORD: secret
    depends_on:
      db:
        condition: service_healthy

  web:
    image: nginx:alpine
    container_name: webvolunteer-web
    ports:
      - "8000:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  db:
    image: postgres:16-alpine
    container_name: webvolunteer-db
    environment:
      POSTGRES_DB: webvolunteer
      POSTGRES_USER: webvolunteer
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U webvolunteer"]
      interval: 5s
      timeout: 5s
      retries: 5

volumes:
  pgdata:
```

- [ ] **Step 4: Validasi config dan nyalakan database**

```bash
docker compose config --quiet
docker compose up -d --build db
docker compose exec db pg_isready -U webvolunteer
```

Expected: `config` tanpa error; `pg_isready` mencetak `accepting connections`.

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml docker/php/Dockerfile docker/nginx/default.conf
git commit -m "chore: docker dev env (php 8.4, postgres 16, nginx)"
```

---

