# Install Informations

The best way to deploy UOPF is to use **Docker Compose**.

1. Create a directory to serve as the working directory for UOPF. Inside it, create a file named `compose.yaml`.

2. Copy the following content into `compose.yaml` and adjust the environment variables as needed:

```yaml
services:
  uopf:
    image: ghcr.io/yanjun1337/uopf-backend
    container_name: uopf
    restart: unless-stopped

    environment:
      TZ: America/Los_Angeles
      UOPF_ENV: production

      UOPF_CACHE_ENGINE: redis
      UOPF_CACHE_REDIS_HOST: redis
      UOPF_CACHE_REDIS_PORT: 6379

      UOPF_DB_HOST: mariadb
      UOPF_DB_NAME: uopf
      UOPF_DB_USERNAME: uopf
      UOPF_DB_PASSWORD: mariadb

      UOPF_HCAPTCHA_SECRET: {Your hCaptcha secret}
      UOPF_HCAPTCHA_SITEKEY: {Your hCaptcha sitekey}

      UOPF_SMTP_HOSTNAME: {SMTP Hostname}
      UOPF_SMTP_USERNAME: {SMTP Username}
      UOPF_SMTP_PASSWORD: {SMTP Password}
      UOPF_SMTP_SECURE: ssl
      UOPF_SMTP_PORT: 465

    ports:
      - 127.0.0.1:9000:9000

    networks:
      - mariadb
      - redis
      - uopf

    volumes:
      - type: bind
        source: /root/uopf/assets
        target: /var/lib/uopf/images

        bind:
          create_host_path: false

  mariadb:
    image: mariadb:12
    container_name: uopf-mariadb
    restart: unless-stopped

    environment:
      MYSQL_ROOT_PASSWORD: mariadb
      MYSQL_DATABASE: uopf
      MYSQL_USER: uopf
      MYSQL_PASSWORD: mariadb

    networks:
      - mariadb

    volumes:
      - type: bind
        source: /root/uopf/database
        target: /var/lib/mysql

        bind:
          create_host_path: false

  redis:
    image: redis
    container_name: uopf-redis
    restart: unless-stopped

    networks:
      - redis

networks:
  mariadb:
  redis:
  uopf:
```

3. Create the directories to store the database files and uploaded images.

4. Run the following command inside the working directory:

```bash
docker compose up -d
```

5. After the containers are running, initialize UOPF using:

```bash
docker exec -it uopf ./uopf init -e admin@uopf.edu -u uopf
```

- `-e` - Email address for the administrator account.
- `-u` - Username for the administrator account.
