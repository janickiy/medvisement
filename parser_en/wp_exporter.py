from __future__ import annotations

from dataclasses import dataclass
import shutil
from typing import Any

import database as db_module


@dataclass
class WpSettings:
    method: str  # "cli" | "rest"
    rest_base_url: str = ""
    rest_username: str = ""
    rest_app_password: str = ""
    rest_verify_ssl: bool = False


def get_wp_settings() -> WpSettings:
    method = db_module.get_setting("wp_export_method", "cli") or "cli"
    rest_base_url = db_module.get_setting("wp_rest_base_url", "").rstrip("/")
    rest_username = db_module.get_setting("wp_rest_username", "")
    rest_app_password = db_module.get_setting("wp_rest_app_password", "")

    # В контейнере parser_en команды wp обычно нет. Если REST-настройки заполнены,
    # не даем массовой выгрузке падать с "No such file or directory: 'wp'".
    if method == "cli" and shutil.which("wp") is None and rest_base_url and rest_username and rest_app_password:
        method = "rest"

    return WpSettings(
        method=method,
        rest_base_url=rest_base_url,
        rest_username=rest_username,
        rest_app_password=rest_app_password,
        rest_verify_ssl=(db_module.get_setting("wp_rest_verify_ssl", "false").lower() in {"1", "true", "yes", "on"}),
    )


def export_disease_article(
    *,
    title: str,
    content_html: str,
    external_id: str,
    status: str = "draft",
    article_type_slug: str = "eng-articles",
    is_english: bool = True,
    age_slugs: list[str] | None = None,
    symptom_names_or_slugs: list[str] | None = None,
    specialty_slugs: list[str] | None = None,
    meta_extra: dict[str, Any] | None = None,
) -> str | None:
    settings = get_wp_settings()
    if settings.method == "rest":
        from wp_rest_client import upsert_disease_post_rest

        return upsert_disease_post_rest(
            base_url=settings.rest_base_url,
            username=settings.rest_username,
            app_password=settings.rest_app_password,
            verify_ssl=settings.rest_verify_ssl,
            title=title,
            content=content_html,
            external_id=external_id,
            status=status,
            article_type_slug=article_type_slug,
            is_english=is_english,
            age_slugs=age_slugs,
            symptom_names_or_slugs=symptom_names_or_slugs,
            specialty_slugs=specialty_slugs,
            meta_extra=meta_extra,
        )

    # default: CLI
    from wp_utils import upsert_disease_post

    return upsert_disease_post(
        title=title,
        content=content_html,
        external_id=external_id,
        status=status,
        article_type_slug=article_type_slug,
        age_slugs=age_slugs or ["adult"],
        symptom_names_or_slugs=symptom_names_or_slugs,
        specialty_slugs=specialty_slugs,
        meta_extra=meta_extra,
        is_english=is_english,
    )
