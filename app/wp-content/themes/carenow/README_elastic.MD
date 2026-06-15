### Лемматизатор

1) Установка
apt install python3 -y
apt install python3-pip -y
pip install flask pymorphy3 gunicorn

2) Запуск
gunicorn app:app --workers 3 --bind 127.0.0.1:5000 --preload --timeout 60

3) Автозапуск
скрипт в /home/medvisement/morph-api/app.py

vim /etc/systemd/system/morph-api.service

```
[Unit]
Description=Morphology API
After=network.target

[Service]
User=medvisement
Group=medvisement
WorkingDirectory=/home/medvisement/morph-api/
Environment="PATH=/usr/local/bin"
ExecStart=/usr/local/bin/gunicorn app:app --workers 3 --bind 127.0.0.1:5000 --preload --timeout 30
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

sudo systemctl daemon-reload
sudo systemctl enable morph-api
sudo systemctl start morph-api

4) Если изменили код скрипта

sudo systemctl restart morph-api

5) Nginx

Чтобы АПИ было доступно извне (для быстрого поиска), проксируем через Nginx

```
location /lemmatize {
  # only allow POST requests
  limit_except POST {
    deny all;
  }

  # Perform our request
  proxy_set_header Host $host;

  # Use the URL of the server here
  proxy_pass http://localhost:5000;
}
```