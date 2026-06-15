FROM python:3.10.12-slim

WORKDIR /app

COPY requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

COPY . /app

ARG APP_UID
ARG APP_GID
ARG APP_USER

RUN groupadd -g ${APP_GID} ${APP_USER} \
 && useradd -u ${APP_UID} -g ${APP_GID} -m ${APP_USER} || true

USER ${APP_UID}:${APP_GID}

CMD ["python", "main.py"]
