Инструкция

1) Создайте проксирование через nginx. Каждый порт должен быть уникальным в рамках сервер-сайт.\
Для подключения используется ссылка формата:
```
wss://<домен>/websocket
```

Конфиг 

```
location /websocket {
  keepalive_timeout  120s 120s;
  keepalive_requests 100000;
  proxy_connect_timeout 120s;
  proxy_send_timeout 120s;
  proxy_read_timeout 120s;
  # этот порт указывается в настройке "Workerman: websocket порт"
  proxy_pass http://127.0.0.1:2346;
  proxy_http_version 1.1;
  proxy_set_header Upgrade $http_upgrade;
  proxy_set_header Connection "Upgrade";
  proxy_set_header X-Real-IP $remote_addr;
}
```

3) Websocket подключения обрабатываются библиотекой workerman.\
   Вы можете запустить скрипт вручную или настроить демон для автоматического поднятия.\
   На примере supervisord:

```
[program:workerman-prod]
command=/usr/bin/php /var/www/medvisement.com/html/wp-content/plugins/medvise-otp-login/websockets.php start
process_name=%(program_name)s_%(process_num)02d
numprocs=1
priority=999
autostart=true
autorestart=true
stopsignal=INT
startsecs=0
startretries=5
user=medvisement
redirect_stderr=true
stdout_logfile=/var/www/medvisement.com/html/wp-content/plugins/medvise-otp-login/logs/workerman-prod.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=0
```

4) Пропишите настройки плагина в секции "Настройки" -> "Интеграция Telegram" Wordpress.

5) При изменении скрипта websockets.php необходимо обновление демона.
   supervisorctl update или supervisorctl restart \*:\*
   