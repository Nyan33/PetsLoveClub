# PetLove Club - развёртывание в Docker

Почти plug-and-play стек для PetsLove Club. Хостовой nginx
терминирует TLS, а compose-файл поднимает за ним три контейнера:

| Сервис        | Образ            | Порт хоста       | Назначение                |
|---------------|------------------|------------------|---------------------------|
| `app`         | локальная сборка | `127.0.0.1:8080` | Apache + PHP 8.2, сайт    |
| `db`          | `mariadb:11`     | только внутренний | База данных              |
| `phpmyadmin`  | `phpmyadmin:5`   | `127.0.0.1:8081` | Веб-интерфейс к БД        |

База данных **не** проброшена на хост - phpMyAdmin и приложение ходят к ней
по внутренней сети compose. Контейнеры `app` и `phpmyadmin` слушают только
`127.0.0.1`, поэтому единственная публичная точка входа - это nginx.

## Структура

```
docker/
  Dockerfile                        образ PHP 8.2 + Apache (на базе php:8.2-apache)
  docker-compose.yml                стек из трёх сервисов
  env-example                      скопировать в .env, заполнить пароли и APP_URL
  apache/000-default.conf           vhost Apache (учитывает X-Forwarded-Proto)
  php/php.ini                       лимиты загрузки, таймзона, лог ошибок
  config/config.example.php         скопировать в config/config.php - там SMTP
  nginx/site.conf  готовый конфиг для хостового nginx
  README.md                         этот файл
```

Контекст сборки - корень проекта (`..`). Образ запекает исходники сайта
в `/var/www/html`; снаружи как volume пробрасываются только `uploads/` и
`includes/config.php`, чтобы они переживали пересборку.

## Первый деплой

1. Склонировать репозиторий на сервер, перейти в `docker/`.
2. `cp env-example .env` и отредактировать:
   - `DB_PASSWORD`, `DB_ROOT_PASSWORD` - задать сильные пароли.
   - `APP_URL` - изменить на действительный адрес сайта (используется в
     ссылках из писем верификации и сброса пароля).
3. `cp config/config.example.php config/config.php` и подставить реальные
   SMTP-данные. Этот шаг можно пропустить - сайт будет работать в «открытом
   режиме» без подтверждения email, но и сброс пароля при этом отключится.
4. `docker compose up -d --build`
5. На первом запуске том БД пустой, поэтому MariaDB автоматически
   импортирует `sql/petlove_club.sql`, а затем `sql/migrate.sql` из
   репозитория. Подождите ~30 секунд, пока healthcheck станет зелёным.
6. Подключить хостовой nginx:
   ```bash
   sudo cp nginx/адрес сайта.conf /etc/nginx/sites-available/
   sudo ln -s /etc/nginx/sites-available/адрес сайта.conf \
              /etc/nginx/sites-enabled/
   sudo certbot --nginx -d адрес сайта
   sudo nginx -t && sudo systemctl reload nginx
   ```

Откройте адрес сайта - зарегистрируйтесь, перейдите по
ссылке из письма, войдите.

## Обновление сайта

```bash
git pull
docker compose up -d --build app
```

Изменения схемы лежат в `sql/migrate.sql`. Применить к работающей БД:

```bash
docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" petlove_club \
  < ../sql/migrate.sql
```

## Частые операции

- **Логи:** `docker compose logs -f app`
- **Шелл в контейнер app:** `docker compose exec app bash`
- **MySQL CLI:** `docker compose exec db mariadb -uroot -p petlove_club`
- **Полный сброс (удаляет данные):**
  `docker compose down -v && docker compose up -d --build`

## Заметки про ссылки в письмах

Сайт читает `APP_URL` (переменная окружения или `app_url` в
`includes/config.php`) и использует это значение как базу для ссылок
верификации и сброса пароля. Если когда-нибудь смените публичный домен -
обновите `APP_URL` в `docker/.env` (и `app_url` в `config/config.php`,
если он там задан) и выполните `docker compose up -d`.

`includes/mail.php :: site_base_url()` берёт значение в таком порядке:
1. `app_url` из `config.php`
2. переменная окружения `APP_URL`
3. `X-Forwarded-Proto` + `Host` от nginx
4. голый `$_SERVER` (только для разработки)

## phpMyAdmin

Доступен по `https://адрес сайта/phpmyadmin/` (path-based,
см. конфиг nginx). Чтобы закрыть его - раскомментируйте строки
`allow ...; deny all;`. Либо вообще не выставляйте наружу и заходите
через SSH-туннель:

```bash
ssh -L 8081:127.0.0.1:8081 server
# затем откройте http://localhost:8081 локально
```
