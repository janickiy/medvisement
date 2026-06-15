## Подготовка сервера

### Пакеты

```shell
sudo update-locale LANG=en_US.UTF-8
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg vim zip

sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### SWAP

```shell
sudo dd if=/dev/zero of=/swapfile bs=1M count=6144 status=progress
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
echo 'vm.swappiness=10' | sudo tee /etc/sysctl.d/99-swappiness.conf
sudo sysctl -p /etc/sysctl.d/99-swappiness.conf
```

### Пользователь

```shell
sudo useradd -m -u 1000 -s /bin/bash medvisement
sudo passwd medvisement
sudo usermod -aG docker medvisement
sudo usermod -aG sudo medvisement
```

### Проект

```shell
su - medvisement
```

```shell
cat <<EOF > ~/.netrc
machine gitlab.lovetirami.su
login medvisement-docker-deploy-token
password gldt-1ZUjgHgVB7TNuAsp-Eya
EOF
chmod 600 ~/.netrc
```

```shell
mkdir -p ~/projects/medvisement
cd ~/projects/medvisement
git clone https://gitlab.lovetirami.su/medvisement/docker.git .
```

### Запускаем Docker

1. Создать .env файл на основе .env.example
```shell
cp .env.example .env
vim .env
```

2. Сборка
```shell
docker compose up -d --build
```

3. SSL Локально выпускаем

```shell
docker run --rm \
  -v ${PWD}/docker/nginx/ssl:/ssl \
  alpine/openssl \
  req -x509 -nodes -days 365 \
  -newkey rsa:2048 \
  -keyout /ssl/privkey.pem \
  -out /ssl/fullchain.pem \
  -subj "/CN=localhost"
  
docker compose up -d nginx
```

4. SSL для домена (при наличии)
```shell
docker compose run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  -d test.medvisement.com \
  --email info@medvisement.com \
  --agree-tos \
  --config-dir /var/www/certbot/letsencrypt
  
sudo cp -f docker/nginx/www/letsencrypt/live/test.medvisement.com/fullchain.pem docker/nginx/ssl/
sudo cp -f docker/nginx/www/letsencrypt/live/test.medvisement.com/privkey.pem docker/nginx/ssl/
docker compose exec nginx nginx -s reload
```

5. SSL для домена (обновление)

```shell
docker compose run --rm certbot renew
docker compose exec nginx nginx -s reload
```

### Разворачиваем бэкап

1. Файлы распаковываем в app/

```shell
tar -xzf archive.tar.gz -C app
```

2. Исправляем права

```shell
chown -R medvisement:medvisement app/
```

3. Импортируем БД

```shell
docker compose exec -T db \
mysql -u root -prootpass medvisement < medvisement.sql
```

### Настройки WordPress

1. Данные для подключения к БД wp-config.php

2. В БД изменяем домен

```mysql
UPDATE `wp_options`
SET `option_value` = 'https://test.medvisement.com'
WHERE `wp_options`.`option_name` = 'siteurl';
UPDATE `wp_options`
SET `option_value` = 'https://test.medvisement.com'
WHERE `wp_options`.`option_name` = 'home';
UPDATE `wp_options`
SET `option_value` = 'http://elasticsearch:9200'
WHERE `wp_options`.`option_name` = 'ep_host';
```

3. Настройки Elasticpress - Autosuggest Endpoint URL

https://test.medvisement.com/wp-admin/admin.php?page=elasticpress

### Cheatsheet / WP CLI

```shell
docker compose exec php bash
docker compose exec php wp --info
```

### Обновление репозиториев в проекте

```shell
docker compose exec php bash /scripts/update-wp-git.sh
```

### Очистка БД от данных
```shell
wp eval-file app-scripts/clear-db.php
```

### Отлов писем Mailpit
Для PHP стоит расширение msmtp
```
SMTP_HOST=mailpit
SMTP_PORT=1025
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=null
```