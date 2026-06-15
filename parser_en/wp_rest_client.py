from __future__ import annotations

import base64
import json
from typing import Any

import requests
import urllib3


def _basic_auth_header(username: str, app_password: str) -> dict[str, str]:
    token = base64.b64encode(f"{username}:{app_password}".encode("utf-8")).decode("ascii")
    return {"Authorization": f"Basic {token}"}


def upsert_disease_post_rest(
    *,
    base_url: str,
    username: str,
    app_password: str,
    verify_ssl: bool = True,
    title: str,
    content: str,
    external_id: str,
    status: str = "draft",
    article_type_slug: str | None = None,
    is_english: bool = False,
    age_slugs: list[str] | None = None,
    symptom_names_or_slugs: list[str] | None = None,
    specialty_slugs: list[str] | None = None,
    meta_extra: dict[str, Any] | None = None,
) -> str | None:
    """
    Upsert через кастомный WP REST endpoint (плагин).
    Возвращает post_id (str) или None.
    """
    if not base_url or not username or not app_password:
        raise RuntimeError("REST export выбран, но не заполнены base_url/username/app_password в настройках.")

    endpoint = f"{base_url}/wp-json/medvise/v1/disease/upsert"
    payload: dict[str, Any] = {
        "title": title,
        "content": content,
        "external_id": external_id,
        "status": status,
        "article_type_slug": article_type_slug,
        "is_english": bool(is_english),
    }

    # Дополнительные поля для таксономий/мета (по аналогии с CLI-вариантом).
    if age_slugs:
        payload["age_slugs"] = list(age_slugs)
    if symptom_names_or_slugs:
        payload["symptom_names_or_slugs"] = list(symptom_names_or_slugs)
    if specialty_slugs:
        payload["specialty_slugs"] = list(specialty_slugs)
    if meta_extra:
        payload["meta_extra"] = dict(meta_extra)

    headers = {
        "Content-Type": "application/json",
        **_basic_auth_header(username, app_password),
    }

    if not verify_ssl:
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    r = requests.post(
        endpoint,
        headers=headers,
        data=json.dumps(payload).encode("utf-8"),
        timeout=120,
        verify=verify_ssl,
    )
    if r.status_code >= 400:
        raise RuntimeError(f"REST export failed ({r.status_code}): {r.text[:500]}")

    data = r.json()
    post_id = data.get("post_id")
    return str(post_id) if post_id else None


def get_disease_status_rest(
    *,
    base_url: str,
    username: str,
    app_password: str,
    verify_ssl: bool = True,
    external_id: str | None = None,
    source_id: str | None = None,
) -> dict[str, Any]:
    """
    Проверка существования записи disease в WordPress по source_id/external_id.
    Возвращает JSON вида:
      { ok: bool, exists: bool, post_id: int, post_status: str, source_id: str }
    """
    if not base_url or not username or not app_password:
        raise RuntimeError("REST выбран, но не заполнены base_url/username/app_password в настройках.")

    endpoint = f"{base_url}/wp-json/medvise/v1/disease/status"
    params: dict[str, Any] = {}
    if external_id:
        params["external_id"] = external_id
    if source_id:
        params["source_id"] = source_id

    headers = {
        **_basic_auth_header(username, app_password),
    }

    if not verify_ssl:
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    r = requests.get(endpoint, headers=headers, params=params, timeout=60, verify=verify_ssl)
    if r.status_code >= 400:
        raise RuntimeError(f"REST status failed ({r.status_code}): {r.text[:500]}")
    data = r.json()
    if not isinstance(data, dict):
        raise RuntimeError("REST status returned non-object JSON")
    return data
