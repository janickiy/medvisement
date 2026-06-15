FROM python:3.10.12-slim

WORKDIR /opt/morph-api

COPY docker/python/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY docker/python/app.py .

EXPOSE 5000

CMD ["gunicorn", \
     "--workers", "3", \
     "--bind", "0.0.0.0:5000", \
     "--preload", \
     "--timeout", "30", \
     "app:app"]

# Юзер
ARG APP_UID
ARG APP_GID
ARG APP_USER

RUN groupadd -g ${APP_GID} ${APP_USER} \
 && useradd -u ${APP_UID} -g ${APP_GID} -m ${APP_USER} || true

USER ${APP_UID}:${APP_GID}