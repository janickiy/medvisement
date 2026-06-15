import logging
import hashlib
import mimetypes
import os
from typing import Any, Dict

import requests
from requests.auth import HTTPBasicAuth

from config import config

logger = logging.getLogger(__name__)


class WordPressClient:
    def __init__(self):
        self.base_url = config.WP_BASE_URL.rstrip("/")
        self.verify_ssl = config.WP_VERIFY_SSL
        self.timeout = config.REQUEST_TIMEOUT
        self.session = requests.Session()
        self.session.auth = HTTPBasicAuth(config.WP_USERNAME, config.WP_PASSWORD)

        if not self.verify_ssl:
            requests.packages.urllib3.disable_warnings()  # type: ignore[attr-defined]

    def _request(self, method: str, path: str, **kwargs) -> Dict[str, Any]:
        url = f"{self.base_url}{path}"
        kwargs.setdefault("timeout", self.timeout)
        kwargs.setdefault("verify", self.verify_ssl)

        headers = kwargs.pop("headers", {})
        headers.setdefault("Accept", "application/json")

        response = self.session.request(method, url, headers=headers, **kwargs)
        response.raise_for_status()

        payload = response.json()
        if not isinstance(payload, dict):
            raise ValueError(f"Unexpected response payload from {url}")

        return payload

    def get_disease_status(self, external_id: str) -> Dict[str, Any]:
        try:
            return self._request(
                "GET",
                "/wp-json/medvise/v1/disease/status",
                params={"external_id": external_id},
            )
        except Exception as exc:
            logger.warning("Не удалось получить статус disease для %s: %s", external_id, exc)
            return {
                "ok": False,
                "exists": False,
                "post_id": 0,
                "post_status": "",
                "source_id": f"pi_{external_id}",
                "attachments_synced": False,
                "attachment_count": 0,
                "pdf_attachment_id": 0,
                "error": str(exc),
            }

    def upsert_disease(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        external_id = payload.get("external_id", "")

        try:
            response = self._request(
                "POST",
                "/wp-json/medvise/v1/disease/upsert",
                json=payload,
                headers={"Content-Type": "application/json"},
            )
        except Exception as exc:
            logger.error("Ошибка WordPress upsert для %s: %s", external_id, exc)
            return {"ok": False, "error": str(exc), "post_id": 0}

        if not response.get("ok"):
            logger.error("WordPress upsert вернул ошибку для %s: %s", external_id, response)

        return response

    def upload_disease_attachment(
        self,
        post_id: int,
        external_id: str,
        file_path: str,
        file_key: str,
        file_hash: str,
        attachment_role: str = "file",
    ) -> Dict[str, Any]:
        mime_type = mimetypes.guess_type(file_path)[0] or "application/octet-stream"

        try:
            with open(file_path, "rb") as handle:
                response = self._request(
                    "POST",
                    "/wp-json/medvise/v1/disease/attachment",
                    data={
                        "post_id": str(post_id),
                        "external_id": external_id,
                        "file_key": file_key,
                        "file_hash": file_hash or self._calculate_file_hash(file_path),
                        "attachment_role": attachment_role,
                    },
                    files={
                        "file": (
                            os.path.basename(file_path),
                            handle,
                            mime_type,
                        )
                    },
                )
        except Exception as exc:
            logger.error("Ошибка загрузки вложения %s для %s: %s", file_path, external_id, exc)
            return {"ok": False, "error": str(exc), "attachment_id": 0}

        if not response.get("ok"):
            logger.error("WordPress attachment upload вернул ошибку для %s: %s", file_path, response)

        return response

    def finalize_disease_attachments(
        self,
        post_id: int,
        external_id: str,
        expected_keys: list[str],
    ) -> Dict[str, Any]:
        try:
            response = self._request(
                "POST",
                "/wp-json/medvise/v1/disease/attachments/finalize",
                json={
                    "post_id": post_id,
                    "external_id": external_id,
                    "expected_keys": expected_keys,
                },
                headers={"Content-Type": "application/json"},
            )
        except Exception as exc:
            logger.error("Ошибка финализации вложений для %s: %s", external_id, exc)
            return {"ok": False, "error": str(exc), "attachment_ids": []}

        if not response.get("ok"):
            logger.error("WordPress attachment finalize вернул ошибку для %s: %s", external_id, response)

        return response

    @staticmethod
    def _calculate_file_hash(file_path: str) -> str:
        digest = hashlib.md5()

        with open(file_path, "rb") as handle:
            while True:
                chunk = handle.read(8192)
                if not chunk:
                    break
                digest.update(chunk)

        return digest.hexdigest()
