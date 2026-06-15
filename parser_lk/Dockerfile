FROM python:3.9-slim

WORKDIR /app

# Install system dependencies (if needed, e.g., for lxml). For now we use stdlib parser.
# RUN apt-get update && apt-get install -y --no-install-recommends \
#     && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Defaults (override via docker-compose/.env). Do not hardcode real keys here.
ENV DELAY_BETWEEN_PAGES=1
ENV DELAY_BETWEEN_PDF=1
ENV CAPTCHA_IMAGES_DIR=captcha_images
ENV PDF_DIR=pdf_files
EXPOSE 5000

CMD ["python", "app.py"]
