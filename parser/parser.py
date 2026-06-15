import re
import os
import json
import hashlib
from typing import Dict, Any, List, Tuple, Optional
from bs4 import BeautifulSoup, Tag, NavigableString
import html
from dataclasses import dataclass
from enum import Enum
import requests
import logging

logger = logging.getLogger(__name__)


class SectionType(Enum):
    ABBREVIATIONS = "abbreviations"
    TERMS = "terms"
    DIAGNOSIS = "diagnosis"
    TREATMENT = "treatment"
    ADDITIONAL_INFO = "additional_info"
    LITERATURE = "literature"
    APPENDICES = "appendices"
    OTHER = "other"


@dataclass
class ParsedSection:
    type: SectionType
    title: str
    content: str
    level: int = 1
    html_content: str = ""


class ContentParser:
    def __init__(self):
        self.sections: List[ParsedSection] = []
        self.literature_section: Optional[ParsedSection] = None

    def parse_from_json_sections(self, sections_data: List[Dict]) -> Tuple[str, str]:
        """
        Парсинг напрямую из JSON структуры sections с учетом иерархии

        Args:
            sections_data: Список разделов из detail_data['obj']['sections']

        Returns:
            Tuple[str, str]: (formatted_text, formatted_html)
        """
        # Строим дерево разделов по ID
        sections_tree = self._build_sections_tree(sections_data)

        # Форматируем дерево в HTML и текст
        formatted_text, formatted_html = self._format_sections_tree(sections_tree)

        return formatted_text, formatted_html

    def _build_sections_tree(self, sections_data: List[Dict]) -> List[Dict]:
        """
        Строим иерархическое дерево разделов на основе ID
        """
        tree = []
        sections_by_id = {}

        # Первый проход - создаем мапу всех разделов
        for section_data in sections_data:
            section_id = section_data.get('id', '')
            title = section_data.get('title', '')
            content = section_data.get('content', '')

            # Пропускаем служебные
            if section_id in ['doc_whole', 'doc_title', 'doc_key_words']:
                continue

            # Определяем тип раздела
            section_type = self._identify_section_type(title, section_id)

            # Пропускаем А1 и А2
            if section_type == SectionType.APPENDICES:
                title_lower = title.lower()
                if 'а1' in title_lower or 'а2' in title_lower:
                    logger.info(f"Пропускаем приложение: {title} ({section_id})")
                    continue

            # ВАЖНО: НЕ пропускаем пустые разделы если они могут быть родителями!
            # Например doc_1, doc_diag_2 могут быть пустыми, но иметь детей
            is_potential_parent = (
                    section_id in ['doc_1', 'doc_diag_2', 'doc_3', 'doc_4', 'doc_5', 'doc_6', 'doc_7']
            )

            is_empty = self._is_empty_content(content)

            if is_empty and not is_potential_parent:
                # Пропускаем только если пустой И не может быть родителем
                logger.info(f"Пропускаем пустой раздел: {title} ({section_id})")
                continue

            sections_by_id[section_id] = {
                'id': section_id,
                'title': title,
                'content': content,
                'type': section_type,
                'children': [],
                'is_empty': is_empty
            }

        # Второй проход - строим иерархию
        for section_id, section in sections_by_id.items():
            # Определяем родителя по паттерну ID
            parent_id = self._find_parent_id(section_id, sections_by_id)

            if parent_id and parent_id in sections_by_id:
                # Это дочерний раздел
                logger.debug(f"Раздел {section_id} ({section['title']}) → дочерний для {parent_id}")
                sections_by_id[parent_id]['children'].append(section)
            else:
                # Это корневой раздел
                logger.debug(f"Раздел {section_id} ({section['title']}) → корневой")
                tree.append(section)

        # Третий проход - удаляем пустые разделы БЕЗ детей
        def remove_empty_without_children(sections):
            result = []
            for section in sections:
                # Рекурсивно очищаем детей
                section['children'] = remove_empty_without_children(section['children'])

                # Оставляем раздел если:
                # 1. Он не пустой ИЛИ
                # 2. У него есть дети
                if not section['is_empty'] or section['children']:
                    result.append(section)
                else:
                    logger.info(f"Удаляем пустой раздел без детей: {section['title']} ({section['id']})")

            return result

        tree = remove_empty_without_children(tree)

        # Литературу перемещаем в конец
        literature = None
        tree_without_lit = []
        for section in tree:
            if section['type'] == SectionType.LITERATURE:
                literature = section
            else:
                tree_without_lit.append(section)

        if literature:
            tree_without_lit.append(literature)

        logger.info(f"Построено дерево разделов: {len(tree_without_lit)} корневых разделов")

        return tree_without_lit

    def _find_parent_id(self, section_id: str, sections_by_id: dict) -> Optional[str]:
        """
        Находим родительский ID по паттерну

        Примеры:
        doc_crat_info_1_1 -> doc_1
        doc_crat_info_1_2 -> doc_1
        doc_diag_2_1 -> doc_diag_2
        doc_diag_2_2 -> doc_diag_2
        """
        # Паттерны дочерних разделов для "1. Краткая информация"
        if section_id.startswith('doc_crat_info_'):
            # doc_crat_info_1_1 -> doc_1
            return 'doc_1'

        # Паттерны дочерних разделов для "2. Диагностика"
        if section_id.startswith('doc_diag_') and section_id != 'doc_diag_2':
            # doc_diag_2_1, doc_diag_2_2 -> doc_diag_2
            return 'doc_diag_2'

        # Паттерны для других разделов с подразделами
        # doc_4_1 -> doc_4 (если есть)
        # doc_5_1 -> doc_5 (если есть)
        if '_' in section_id:
            parts = section_id.split('_')
            if len(parts) >= 2:
                # Проверяем есть ли родительский раздел
                potential_parent = '_'.join(parts[:-1])
                if potential_parent in sections_by_id:
                    return potential_parent

        # Для остальных - нет родителя
        return None

    def _is_empty_content(self, content: str) -> bool:
        """Проверка что контент пустой"""
        if not content:
            return True

        # Парсим HTML
        soup = BeautifulSoup(content, 'html.parser')
        text = soup.get_text().strip()

        # Пустой если только пробелы или <br>
        return len(text) == 0

    def _format_sections_tree(self, tree: List[Dict]) -> Tuple[str, str]:
        """Форматирование дерева разделов"""
        formatted_text_parts = []
        formatted_html_parts = []

        for section in tree:
            text, html = self._format_tree_section(section)
            if text and html:  # Пропускаем пустые
                formatted_text_parts.append(text)
                formatted_html_parts.append(html)

        return "\n\n".join(formatted_text_parts), "\n\n".join(formatted_html_parts)

    def _format_tree_section(self, section: Dict) -> Tuple[str, str]:
        """
        Форматирование одного раздела дерева с детьми
        """
        section_id = section['id']
        title = section['title']
        content = section['content']
        section_type = section['type']
        children = section['children']

        # Для раздела "Лечение" (doc_3) используем специальное форматирование
        if section_id == 'doc_3':
            return self._format_treatment_section(title, content)

        # Для приложений с длинным контентом используем специальное форматирование
        if section_type == SectionType.APPENDICES and len(self._clean_html_to_text(content)) > 500:
            return self._format_appendix_with_headers(title, content, min_length=500)

        # Для разделов с детьми - НЕ форматируем контент если он пустой
        # (чтобы избежать пустых спойлеров внутри)
        if children and self._is_empty_content(content):
            # Если есть дети и контент пустой - не добавляем контент основного раздела
            text_content = ""
            html_content = ""
        else:
            # Форматируем контент основного раздела
            text_content, html_content = self._format_section_content_by_type(
                content, section_type, title
            )

        # Форматируем дочерние разделы
        children_text = []
        children_html = []

        for child in children:
            child_text, child_html = self._format_tree_section(child)
            if child_text and child_html:
                children_text.append(child_text)
                children_html.append(child_html)

        # Собираем результат
        if children:
            # Есть дети - оборачиваем
            if text_content:  # Есть контент основного раздела
                full_text = f"%{title}%\n%{text_content}\n" + "\n".join(children_text) + "%"
                full_html = (
                        f"<details>\n<summary>{title}</summary>\n"
                        f"{html_content}\n"
                        + "\n".join(children_html) +
                        "\n</details>"
                )
            else:  # Нет контента основного раздела (только дети)
                full_text = f"%{title}%\n" + "\n".join(children_text) + "%"
                full_html = (
                        f"<details>\n<summary>{title}</summary>\n"
                        + "\n".join(children_html) +
                        "\n</details>"
                )
        else:
            # Нет детей - простой спойлер
            full_text = f"%{title}%\n%{text_content}%"
            full_html = f"<details>\n<summary>{title}</summary>\n{html_content}\n</details>"

        return full_text, full_html

    def _format_section_with_headers(self, title: str, content: str, min_length: int = 500) -> Tuple[str, str]:
        """
        Общий метод форматирования раздела с заголовками (как в Лечении)
        Используется для Лечения и для длинных приложений
        """
        if not content or self._is_empty_content(content):
            text_result = f"%{title}%\n%Пустой раздел%"
            html_result = f"<details>\n<summary>{title}</summary>\nПустой раздел\n</details>"
            return text_result, html_result

        # Проверяем длину контента
        text_content = self._clean_html_to_text(content)
        if len(text_content) < min_length:
            # Если контент короткий - простой спойлер без заголовков
            text_result = f"%{title}%\n%{text_content}%"
            html_result = f"<details>\n<summary>{title}</summary>\n{content}\n</details>"
            return text_result, html_result

        # Если контент длинный - парсим заголовки
        soup = BeautifulSoup(content, 'html.parser')
        structure = self._build_structure_by_headers(soup)

        # Если структура не построена (нет заголовков)
        if not structure:
            text_result = f"%{title}%\n%{text_content}%"
            html_result = f"<details>\n<summary>{title}</summary>\n{content}\n</details>"
            return text_result, html_result

        # Форматируем структуру
        return self._format_structure_by_headers(title, structure)

    def _build_structure_by_headers(self, soup: BeautifulSoup) -> List[Dict]:
        """
        Построение иерархической структуры по заголовкам
        Поддерживает различные комбинации заголовков h1-h6
        """
        structure = []

        # Находим все заголовки
        headers = soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])

        # Если нет заголовков, возвращаем весь контент как один раздел
        if not headers:
            return [{
                'title': '',
                'content': [str(soup)],
                'children': []
            }]

        # Собираем заголовки с их контентом
        header_elements = []
        for header in headers:
            # Получаем контент от этого заголовка до следующего заголовка
            content_parts = []
            next_elem = header.next_sibling

            while next_elem:
                if isinstance(next_elem, Tag) and next_elem.name in ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']:
                    break

                if isinstance(next_elem, Tag):
                    content_parts.append(str(next_elem))
                elif isinstance(next_elem, NavigableString):
                    text = str(next_elem).strip()
                    if text:
                        content_parts.append(text)

                next_elem = next_elem.next_sibling

            header_elements.append({
                'element': header,
                'level': int(header.name[1]),  # h1 -> 1, h2 -> 2
                'title': header.get_text().strip(),
                'content': content_parts
            })

        # Определяем минимальный уровень заголовков
        if header_elements:
            min_level = min(h['level'] for h in header_elements)
        else:
            min_level = 2  # По умолчанию

        # Группируем заголовки по уровням
        # Заголовки уровня min_level становятся корневыми разделами
        for header_info in header_elements:
            header_level = header_info['level']
            header_title = header_info['title']
            header_content = ''.join(header_info['content'])

            # Если это заголовок минимального уровня - это корневой раздел
            if header_level == min_level:
                structure.append({
                    'title': header_title,
                    'content': [header_content],
                    'children': [],
                    'has_title_in_content': False  # Флаг, что заголовок уже есть в названии спойлера
                })
            else:
                # Если это подзаголовок - добавляем к последнему корневому разделу
                if structure:
                    # Проверяем уровень: если это h3 и у нас есть h2, добавляем к последнему h2
                    if header_level == min_level + 1:
                        structure[-1]['children'].append({
                            'title': header_title,
                            'content': [header_content],
                            'children': [],
                            'has_title_in_content': False
                        })
                    # Если это h4 и у нас есть h3, добавляем к последнему h3
                    elif header_level == min_level + 2 and structure[-1]['children']:
                        structure[-1]['children'][-1]['children'].append({
                            'title': header_title,
                            'content': [header_content],
                            'children': [],
                            'has_title_in_content': False
                        })
                    else:
                        # Иначе добавляем как контент к последнему разделу
                        structure[-1]['content'].append(f"<h{header_level}>{header_title}</h{header_level}>")
                        structure[-1]['content'].append(header_content)
                else:
                    # Если нет корневых разделов, создаем один
                    structure.append({
                        'title': '',
                        'content': [f"<h{header_level}>{header_title}</h{header_level}>", header_content],
                        'children': [],
                        'has_title_in_content': True  # В этом случае заголовок уже в контенте
                    })

        # Обрабатываем контент перед первым заголовком (если есть)
        first_header = headers[0] if headers else None
        if first_header:
            # Собираем контент перед первым заголовком
            pre_content_parts = []
            prev_elem = first_header.previous_sibling

            while prev_elem:
                if isinstance(prev_elem, Tag):
                    pre_content_parts.insert(0, str(prev_elem))
                elif isinstance(prev_elem, NavigableString):
                    text = str(prev_elem).strip()
                    if text:
                        pre_content_parts.insert(0, text)

                prev_elem = prev_elem.previous_sibling

            if pre_content_parts:
                # Если есть контент перед первым заголовком, добавляем его как отдельный раздел
                structure.insert(0, {
                    'title': '',
                    'content': pre_content_parts,
                    'children': [],
                    'has_title_in_content': False
                })

        return structure

    def _format_structure_by_headers(self, main_title: str, structure: List[Dict]) -> Tuple[str, str]:
        """Форматирование структуры с заголовками в HTML и текст"""
        if not structure:
            return "", ""

        html_parts = []
        text_parts = []

        # Форматируем каждый корневой раздел
        for node in structure:
            node_html, node_text = self._format_structure_node(node)
            html_parts.append(node_html)
            text_parts.append(node_text)

        # Объединяем все разделы
        all_html = "\n".join(html_parts)
        all_text = "\n".join(text_parts)

        # Оборачиваем в основной спойлер с заголовком раздела
        final_html = f"<details>\n<summary>{main_title}</summary>\n{all_html}\n</details>"
        final_text = f"%{main_title}%\n{all_text}%"

        return final_text, final_html

    def _format_structure_node(self, node: Dict) -> Tuple[str, str]:
        """Форматирование одного узла структуры с заголовками"""
        title = node['title']
        content = ''.join(node['content'])
        children = node['children']
        has_title_in_content = node.get('has_title_in_content', False)

        # Форматируем детей
        children_html_parts = []
        children_text_parts = []

        for child in children:
            child_html, child_text = self._format_structure_node(child)
            children_html_parts.append(child_html)
            children_text_parts.append(child_text)

        children_html = "\n".join(children_html_parts) if children_html_parts else ""
        children_text = "\n".join(children_text_parts) if children_text_parts else ""

        # Очищаем контент от HTML тегов для текстовой версии
        cleaned_content = self._clean_html_to_text(content) if content else ""

        if title:
            # Если есть заголовок, оборачиваем в спойлер
            # Если заголовок уже есть в контенте, удаляем его оттуда, чтобы избежать дублирования
            if not has_title_in_content:
                # Пытаемся удалить заголовок из контента
                soup = BeautifulSoup(content, 'html.parser')

                # Ищем заголовки h1-h6 с таким же текстом
                for header in soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']):
                    if header.get_text().strip() == title:
                        header.decompose()

                # Ищем теги strong/b с таким же текстом (для приложений)
                for strong in soup.find_all(['strong', 'b']):
                    strong_text = strong.get_text().strip()
                    # Проверяем, начинается ли текст с цифры и точки (например, "1. ")
                    import re
                    if re.match(r'^\d+\.\s+', strong_text):
                        # Если текст совпадает с заголовком, удаляем весь родительский элемент
                        if strong_text == title:
                            parent = strong.parent
                            if parent:
                                parent.decompose()
                            else:
                                strong.decompose()

                content = str(soup)
                cleaned_content = self._clean_html_to_text(content)

            html_result = f"<details>\n<summary>{title}</summary>\n{content}\n{children_html}\n</details>"
            text_result = f"%{title}%\n%{cleaned_content}\n{children_text}%" if cleaned_content else f"%{title}%\n{children_text}%"
        else:
            # Без заголовка - просто контент
            html_result = content + ("\n" + children_html if children_html else "")
            text_result = cleaned_content + ("\n" + children_text if children_text else "")

        return html_result, text_result

    def _format_treatment_section(self, title: str, content: str) -> Tuple[str, str]:
        """Специальное форматирование для раздела 'Лечение'"""
        return self._format_section_with_headers(title, content,
                                                 min_length=0)  # Для лечения всегда обрабатываем заголовки

    def _build_treatment_structure(self, soup: BeautifulSoup) -> List[Dict]:
        """
        Построение иерархической структуры для раздела лечения
        Теперь правильно обрабатывает заголовки одного уровня
        """
        structure = []

        # Находим все заголовки
        headers = soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])

        # Если нет заголовков, возвращаем весь контент как один раздел
        if not headers:
            # Создаем один раздел без заголовка
            return [{
                'title': '',
                'content': [str(soup)],
                'children': []
            }]

        # Сортируем заголовки по их позиции в документе
        header_elements = []
        for header in headers:
            # Получаем контент от этого заголовка до следующего заголовка
            content_parts = []
            next_elem = header.next_sibling

            while next_elem:
                if isinstance(next_elem, Tag) and next_elem.name in ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']:
                    break

                if isinstance(next_elem, Tag):
                    content_parts.append(str(next_elem))
                elif isinstance(next_elem, NavigableString):
                    text = str(next_elem).strip()
                    if text:
                        content_parts.append(text)

                next_elem = next_elem.next_sibling

            header_elements.append({
                'element': header,
                'level': int(header.name[1]),  # h1 -> 1, h2 -> 2
                'title': header.get_text().strip(),
                'content': content_parts
            })

        # Определяем минимальный уровень заголовков
        if header_elements:
            min_level = min(h['level'] for h in header_elements)
        else:
            min_level = 2  # По умолчанию

        # Группируем заголовки по уровням
        # Заголовки уровня min_level становятся корневыми разделами
        for header_info in header_elements:
            header_level = header_info['level']
            header_title = header_info['title']
            header_content = ''.join(header_info['content'])

            # Если это заголовок минимального уровня - это корневой раздел
            if header_level == min_level:
                structure.append({
                    'title': header_title,
                    'content': [header_content],
                    'children': []
                })
            else:
                # Если это подзаголовок - добавляем к последнему корневому разделу
                if structure:
                    # Проверяем уровень: если это h3 и у нас есть h2, добавляем к последнему h2
                    if header_level == min_level + 1:
                        structure[-1]['children'].append({
                            'title': header_title,
                            'content': [header_content],
                            'children': []
                        })
                    # Если это h4 и у нас есть h3, добавляем к последнему h3
                    elif header_level == min_level + 2 and structure[-1]['children']:
                        structure[-1]['children'][-1]['children'].append({
                            'title': header_title,
                            'content': [header_content],
                            'children': []
                        })
                    else:
                        # Иначе добавляем как контент к последнему разделу
                        structure[-1]['content'].append(f"<h{header_level}>{header_title}</h{header_level}>")
                        structure[-1]['content'].append(header_content)
                else:
                    # Если нет корневых разделов, создаем один
                    structure.append({
                        'title': '',
                        'content': [f"<h{header_level}>{header_title}</h{header_level}>", header_content],
                        'children': []
                    })

        # Обрабатываем контент перед первым заголовком (если есть)
        first_header = headers[0] if headers else None
        if first_header:
            # Собираем контент перед первым заголовком
            pre_content_parts = []
            prev_elem = first_header.previous_sibling

            while prev_elem:
                if isinstance(prev_elem, Tag):
                    pre_content_parts.insert(0, str(prev_elem))
                elif isinstance(prev_elem, NavigableString):
                    text = str(prev_elem).strip()
                    if text:
                        pre_content_parts.insert(0, text)

                prev_elem = prev_elem.previous_sibling

            if pre_content_parts:
                # Если есть контент перед первым заголовком, добавляем его как отдельный раздел
                structure.insert(0, {
                    'title': '',
                    'content': pre_content_parts,
                    'children': []
                })

        return structure

    def _format_treatment_node(self, node: Dict) -> Tuple[str, str]:
        """Форматирование одного узла структуры лечения"""
        title = node['title']
        content = ''.join(node['content'])
        children = node['children']

        # Форматируем детей
        children_html_parts = []
        children_text_parts = []

        for child in children:
            child_html, child_text = self._format_treatment_node(child)
            children_html_parts.append(child_html)
            children_text_parts.append(child_text)

        children_html = "\n".join(children_html_parts) if children_html_parts else ""
        children_text = "\n".join(children_text_parts) if children_text_parts else ""

        # Очищаем контент от HTML тегов для текстовой версии
        cleaned_content = self._clean_html_to_text(content) if content else ""

        if title:
            # Если есть заголовок, оборачиваем в спойлер
            html_result = f"<details>\n<summary>{title}</summary>\n{content}\n{children_html}\n</details>"
            text_result = f"%{title}%\n%{cleaned_content}\n{children_text}%" if cleaned_content else f"%{title}%\n{children_text}%"
        else:
            # Без заголовка - просто контент
            html_result = content + ("\n" + children_html if children_html else "")
            text_result = cleaned_content + ("\n" + children_text if children_text else "")

        return html_result, text_result

    def _format_treatment_structure(self, main_title: str, structure: List[Dict]) -> Tuple[str, str]:
        """Форматирование структуры лечения в HTML и текст"""
        if not structure:
            return "", ""

        html_parts = []
        text_parts = []

        # Форматируем каждый корневой раздел
        for node in structure:
            node_html, node_text = self._format_treatment_node(node)
            html_parts.append(node_html)
            text_parts.append(node_text)

        # Объединяем все разделы
        all_html = "\n".join(html_parts)
        all_text = "\n".join(text_parts)

        # Оборачиваем в основной спойлер с заголовком раздела
        final_html = f"<details>\n<summary>{main_title}</summary>\n{all_html}\n</details>"
        final_text = f"%{main_title}%\n{all_text}%"

        return final_text, final_html

    def _format_appendix_with_headers(self, title: str, content: str, min_length: int = 500) -> Tuple[str, str]:
        """
        Специальное форматирование для приложений с нумерованными пунктами
        """
        if not content or self._is_empty_content(content):
            text_result = f"%{title}%\n%Пустой раздел%"
            html_result = f"<details>\n<summary>{title}</summary>\nПустой раздел\n</details>"
            return text_result, html_result

        # Проверяем длину контента
        text_content = self._clean_html_to_text(content)
        if len(text_content) < min_length:
            # Если контент короткий - простой спойлер без заголовков
            text_result = f"%{title}%\n%{text_content}%"
            html_result = f"<details>\n<summary>{title}</summary>\n{content}\n</details>"
            return text_result, html_result

        # Сначала проверяем, есть ли структурированные заголовки (h1-h6)
        soup = BeautifulSoup(content, 'html.parser')

        # Ищем заголовки h1-h6
        headers = soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])

        if headers:
            # Если есть обычные заголовки - используем существующую логику
            structure = self._build_structure_by_headers(soup)
            if structure:
                return self._format_structure_by_headers(title, structure)

        # Если нет заголовков, ищем нумерованные пункты в тегах <strong> или <b>
        structure = self._build_appendix_structure(soup)

        if structure:
            # Форматируем найденную структуру
            return self._format_appendix_structure(title, structure)
        else:
            # Если структуры нет - простой спойлер
            text_result = f"%{title}%\n%{text_content}%"
            html_result = f"<details>\n<summary>{title}</summary>\n{content}\n</details>"
            return text_result, html_result

    def _build_appendix_structure(self, soup: BeautifulSoup) -> List[Dict]:
        """
        Построение структуры приложений на основе нумерованных пунктов
        Находит пункты вида "1. ", "2. ", "3. " в тегах <strong> или <b>
        """
        import re

        structure = []
        current_section = None
        current_content = []

        # Проходим по всем элементам
        for element in soup.children:
            if not isinstance(element, Tag):
                # Текстовый контент - добавляем к текущему разделу
                if current_section is not None:
                    text = str(element).strip()
                    if text:
                        current_content.append(text)
                continue

            # Проверяем, является ли элемент потенциальным заголовком раздела
            is_header = False
            header_title = ""

            # Проверяем разные варианты заголовков
            if element.name in ['p', 'div']:
                # Ищем strong/b теги внутри
                strong_elements = element.find_all(['strong', 'b'])
                for strong in strong_elements:
                    text = strong.get_text().strip()
                    # Проверяем, начинается ли текст с цифры и точки (например, "1. ", "2. ")
                    if re.match(r'^\d+\.\s+', text):
                        is_header = True
                        header_title = text
                        # Удаляем strong тег из элемента, чтобы избежать дублирования
                        strong.decompose()
                        # Оставляем только оставшийся контент
                        remaining_content = str(element)
                        if remaining_content.strip():
                            current_content.append(remaining_content)
                        break

            elif element.name in ['strong', 'b']:
                text = element.get_text().strip()
                if re.match(r'^\d+\.\s+', text):
                    is_header = True
                    header_title = text
                    # Не добавляем этот элемент в контент

            if is_header:
                # Сохраняем предыдущий раздел
                if current_section is not None:
                    current_section['content'] = current_content
                    structure.append(current_section)

                # Начинаем новый раздел
                current_section = {
                    'title': header_title,
                    'content': []
                }
                current_content = []

                # НЕ добавляем элемент как контент (чтобы избежать дублирования)
            else:
                # Обычный контент - добавляем к текущему разделу
                if current_section is not None:
                    current_content.append(str(element))
                else:
                    # Контент перед первым заголовком - создаем раздел без заголовка
                    if not structure or structure[-1]['title']:
                        structure.append({
                            'title': '',
                            'content': [str(element)]
                        })
                    else:
                        structure[-1]['content'].append(str(element))

        # Добавляем последний раздел
        if current_section is not None:
            current_section['content'] = current_content
            structure.append(current_section)

        # Если структура не найдена, но есть контент
        if not structure:
            # Проверяем, есть ли в контенте нумерованные списки
            text = soup.get_text()
            if re.search(r'\n\d+\.\s+', text):
                # Пробуем разбить по нумерованным пунктам
                lines = text.split('\n')
                sections = []
                current_section = {'title': '', 'content': []}

                for line in lines:
                    line = line.strip()
                    if re.match(r'^\d+\.\s+', line):
                        if current_section['content']:
                            sections.append(current_section)
                        current_section = {'title': line, 'content': []}
                    elif line:
                        current_section['content'].append(line)

                if current_section['content'] or current_section['title']:
                    sections.append(current_section)

                if len(sections) > 1:
                    return sections

        return structure

    def _format_appendix_structure(self, main_title: str, structure: List[Dict]) -> Tuple[str, str]:
        """Форматирование структуры приложения в HTML и текст"""
        if not structure:
            return "", ""

        html_parts = []
        text_parts = []

        # Форматируем каждый раздел
        for section in structure:
            title = section['title']
            content = ''.join(section['content'])

            # Очищаем контент от HTML тегов для текстовой версии
            cleaned_content = self._clean_html_to_text(content) if content else ""

            if title:
                # Если есть заголовок, оборачиваем в спойлер
                # Удаляем дублирующий заголовок из контента
                soup = BeautifulSoup(content, 'html.parser')

                # Ищем теги strong/b с таким же текстом
                for strong in soup.find_all(['strong', 'b']):
                    strong_text = strong.get_text().strip()
                    if strong_text == title:
                        # Удаляем весь параграф, содержащий этот strong
                        parent = strong.parent
                        if parent and parent.name == 'p':
                            parent.decompose()
                        else:
                            strong.decompose()

                content = str(soup)
                cleaned_content = self._clean_html_to_text(content)

                html_parts.append(f"<details>\n<summary>{title}</summary>\n{content}\n</details>")
                text_parts.append(f"%{title}%\n%{cleaned_content}%")
            else:
                # Без заголовка - просто контент
                html_parts.append(content)
                text_parts.append(cleaned_content)

        # Объединяем все разделы
        all_html = "\n".join(html_parts)
        all_text = "\n".join(text_parts)

        # Оборачиваем в основной спойлер с заголовком приложения
        final_html = f"<details>\n<summary>{main_title}</summary>\n{all_html}\n</details>"
        final_text = f"%{main_title}%\n{all_text}%"

        return final_text, final_html

    def _format_section_content_by_type(
            self,
            content: str,
            section_type: SectionType,
            title: str
    ) -> Tuple[str, str]:
        """Форматирование контента в зависимости от типа раздела"""

        if section_type == SectionType.ABBREVIATIONS:
            return self._format_abbreviations(content, content)
        elif section_type == SectionType.TERMS:
            return self._format_terms(content, content)
        elif section_type == SectionType.DIAGNOSIS:
            return self._format_diagnosis(content, content)
        elif section_type == SectionType.LITERATURE:
            return self._format_literature(content, content)
        elif section_type == SectionType.APPENDICES:
            return self._format_appendices(title, content, content)
        elif section_type == SectionType.ADDITIONAL_INFO:
            return self._format_additional_info(content, content)
        else:
            # Для остальных - просто очищаем HTML
            return self._clean_html_to_text(content), content

    def _format_all_sections_hierarchical(self) -> Tuple[str, str]:
        """
        Форматирование всех разделов с учетом иерархии

        Создает вложенные спойлеры:
        - h1 содержит все h2 до следующего h1
        - h2 содержит все h3 до следующего h2
        """
        formatted_html_sections = []
        formatted_text_sections = []

        i = 0
        while i < len(self.sections):
            section = self.sections[i]

            # Форматируем текущий раздел
            text_content, html_content = self._format_section_content_only(section)

            # Для h1 - собираем все дочерние h2
            if section.level == 1:
                children_html = []
                children_text = []
                i += 1

                # Собираем все h2 до следующего h1
                while i < len(self.sections) and self.sections[i].level > 1:
                    child_section = self.sections[i]

                    if child_section.level == 2:
                        # h2 и его дочерние h3
                        child_text, child_html = self._format_h2_with_children(child_section, i)
                        children_text.append(child_text)
                        children_html.append(child_html)

                        # Пропускаем обработанные h3
                        i += 1
                        while i < len(self.sections) and self.sections[i].level == 3:
                            i += 1
                    else:
                        i += 1

                # Оборачиваем h1 с детьми
                full_html = f'<details>\n<summary>{section.title}</summary>\n{html_content}\n{"".join(children_html)}\n</details>'
                full_text = f'%{section.title}%\n%{text_content}\n{"".join(children_text)}%'

                formatted_html_sections.append(full_html)
                formatted_text_sections.append(full_text)

            # Для h2 без родителя h1
            elif section.level == 2:
                child_text, child_html = self._format_h2_with_children(section, i)
                formatted_text_sections.append(child_text)
                formatted_html_sections.append(child_html)

                # Пропускаем дочерние h3
                i += 1
                while i < len(self.sections) and self.sections[i].level == 3:
                    i += 1

            # h3 без родителей (не должно быть, но на всякий случай)
            else:
                text_result = f'%{section.title}%\n%{text_content}%'
                html_result = f'<details>\n<summary>{section.title}</summary>\n{html_content}\n</details>'
                formatted_text_sections.append(text_result)
                formatted_html_sections.append(html_result)
                i += 1

        # Добавляем литературу в конец
        if self.literature_section:
            text_content, html_content = self._format_section_content_only(self.literature_section)
            text_result = f'%{self.literature_section.title}%\n%{text_content}%'
            html_result = f'<details>\n<summary>{self.literature_section.title}</summary>\n{html_content}\n</details>'
            formatted_text_sections.append(text_result)
            formatted_html_sections.append(html_result)

        return "\n\n".join(formatted_text_sections), "\n\n".join(formatted_html_sections)

    def _format_h2_with_children(self, h2_section: ParsedSection, start_idx: int) -> Tuple[str, str]:
        """Форматирование h2 со всеми дочерними h3"""
        # Форматируем сам h2
        text_content, html_content = self._format_section_content_only(h2_section)

        # Собираем дочерние h3
        children_html = []
        children_text = []

        i = start_idx + 1
        while i < len(self.sections) and self.sections[i].level == 3:
            h3_section = self.sections[i]
            h3_text, h3_html = self._format_section_content_only(h3_section)

            children_text.append(f'%{h3_section.title}%\n%{h3_text}%')
            children_html.append(f'<details>\n<summary>{h3_section.title}</summary>\n{h3_html}\n</details>')
            i += 1

        # Оборачиваем h2 с детьми
        full_html = f'<details>\n<summary>{h2_section.title}</summary>\n{html_content}\n{"".join(children_html)}\n</details>'
        full_text = f'%{h2_section.title}%\n%{text_content}\n{"".join(children_text)}%'

        return full_text, full_html

    def _format_section_content_only(self, section: ParsedSection) -> Tuple[str, str]:
        """
        Форматирование только содержимого раздела (без оборачивания в спойлер)
        Returns: (text_content, html_content)
        """
        content = section.content
        html_content = section.html_content

        # Обработка в зависимости от типа раздела
        if section.type == SectionType.ABBREVIATIONS:
            text_content, html_content = self._format_abbreviations(content, html_content)
        elif section.type == SectionType.TERMS:
            text_content, html_content = self._format_terms(content, html_content)
        elif section.type == SectionType.DIAGNOSIS:
            text_content, html_content = self._format_diagnosis(content, html_content)
        elif section.type == SectionType.TREATMENT:
            text_content, html_content = self._format_treatment(content, html_content)
        elif section.type == SectionType.LITERATURE:
            text_content, html_content = self._format_literature(content, html_content)
        elif section.type == SectionType.APPENDICES:
            text_content, html_content = self._format_appendices(section.title, content, html_content)
        elif section.type == SectionType.ADDITIONAL_INFO:
            text_content, html_content = self._format_additional_info(content, html_content)
        else:
            text_content = self._clean_html_to_text(content)
            html_content = html_content

        return text_content, html_content

    def parse_html(self, html_content: str) -> Tuple[str, str]:
        """
        Парсинг HTML и форматирование согласно правилам
        Returns: (formatted_text, formatted_html)
        """
        soup = BeautifulSoup(html_content, 'html.parser')

        # Убираем лишние теги
        for tag in soup(['script', 'style', 'meta', 'link']):
            tag.decompose()

        # Находим все основные разделы
        self._extract_sections(soup)

        # Форматируем разделы
        return self._format_all_sections()

    def _extract_sections(self, soup: BeautifulSoup):
        """Извлечение разделов из HTML"""
        self.sections = []
        self.literature_section = None

        # Ищем заголовки h1, h2, h3
        headers = soup.find_all(['h1', 'h2', 'h3'])

        for i, header in enumerate(headers):
            # Получаем содержимое до следующего заголовка
            content_parts = []
            html_parts = []
            next_elem = header.next_sibling

            while next_elem:
                if isinstance(next_elem, Tag) and next_elem.name in ['h1', 'h2', 'h3']:
                    break
                if isinstance(next_elem, Tag):
                    html_parts.append(str(next_elem))
                    content_parts.append(str(next_elem))
                elif isinstance(next_elem, NavigableString):
                    text = str(next_elem).strip()
                    if text:
                        content_parts.append(text)
                        html_parts.append(text)
                next_elem = next_elem.next_sibling

            content = ''.join(content_parts)
            html_content = ''.join(html_parts)

            # Определяем тип раздела по заголовку (без section_id пока)
            header_text = header.get_text().strip()
            section_type = self._identify_section_type(header_text)

            section = ParsedSection(
                type=section_type,
                title=header_text,
                content=content,
                html_content=html_content,
                level=int(header.name[1])  # h1 -> 1, h2 -> 2, h3 -> 3
            )

            # Литературу сохраняем отдельно для перемещения в конец
            if section_type == SectionType.LITERATURE:
                self.literature_section = section
            else:
                self.sections.append(section)

    def _identify_section_type(self, header_text: str, section_id: str = None) -> SectionType:
        """
        Определение типа раздела по ID из JSON или по заголовку

        Args:
            header_text: Текст заголовка раздела
            section_id: ID раздела из JSON (например, 'doc_abbreviation', 'doc_terms')

        Returns:
            SectionType: Тип раздела
        """
        # Приоритет 1: Определение по ID из JSON (самый надежный способ)
        if section_id:
            section_id_lower = section_id.lower()

            # Сокращения
            if 'abbreviation' in section_id_lower or section_id == 'doc_abbreviation':
                return SectionType.ABBREVIATIONS

            # Термины и определения
            if 'terms' in section_id_lower or section_id == 'doc_terms':
                return SectionType.TERMS

            # Диагностика
            if 'diag' in section_id_lower or section_id.startswith('doc_diag'):
                return SectionType.DIAGNOSIS

            # Лечение
            if section_id == 'doc_3' or 'treatment' in section_id_lower:
                return SectionType.TREATMENT

            # Литература
            if 'bible' in section_id_lower or section_id == 'doc_bible':
                return SectionType.LITERATURE

            # Приложения
            if section_id.startswith('doc_a') or 'appendix' in section_id_lower:
                return SectionType.APPENDICES

            # Дополнительная информация
            if section_id == 'doc_7' or section_id == 'doc_6':
                return SectionType.ADDITIONAL_INFO

        # Приоритет 2: Определение по заголовку (fallback)
        header_lower = header_text.lower()

        if any(word in header_lower for word in ['сокращен', 'abbreviation']):
            return SectionType.ABBREVIATIONS
        elif any(word in header_lower for word in ['термин', 'определен', 'definition']):
            return SectionType.TERMS
        elif any(word in header_lower for word in ['диагност', 'diagnos']):
            return SectionType.DIAGNOSIS
        elif any(word in header_lower for word in ['лечен', 'treatment', 'терап']):
            return SectionType.TREATMENT
        elif any(word in header_lower for word in ['дополнительн', 'доп. информац', 'additional']):
            return SectionType.ADDITIONAL_INFO
        elif any(word in header_lower for word in ['литератур', 'список', 'reference', 'библиограф']):
            return SectionType.LITERATURE
        elif any(word in header_lower for word in ['приложен', 'appendix']):
            return SectionType.APPENDICES
        else:
            return SectionType.OTHER

    def _format_section(self, section: ParsedSection) -> Tuple[str, str]:
        """
        Форматирование раздела согласно правилам
        Returns: (text_version, html_version)
        """
        # Обработка в зависимости от типа раздела
        if section.type == SectionType.ABBREVIATIONS:
            text_content, html_content = self._format_abbreviations(section.content, section.html_content)
        elif section.type == SectionType.TERMS:
            text_content, html_content = self._format_terms(section.content, section.html_content)
        elif section.type == SectionType.DIAGNOSIS:
            text_content, html_content = self._format_diagnosis(section.content, section.html_content)
        elif section.type == SectionType.TREATMENT:
            text_content, html_content = self._format_treatment(section.content, section.html_content)
        elif section.type == SectionType.LITERATURE:
            text_content, html_content = self._format_literature(section.content, section.html_content)
        elif section.type == SectionType.APPENDICES:
            text_content, html_content = self._format_appendices(section.title, section.content, section.html_content)
        elif section.type == SectionType.ADDITIONAL_INFO:
            text_content, html_content = self._format_additional_info(section.content, section.html_content)
        else:
            text_content = self._clean_html_to_text(section.content)
            html_content = section.html_content

        # Оборачиваем заголовок и содержимое в спойлер для h1, h2, h3 (п. 2.1)
        if section.level <= 3:
            text_result = f"%{section.title}%\n%{text_content}%"
            html_result = f'<details><summary>{section.title}</summary>\n{html_content}\n</details>'
        else:
            text_result = f"{section.title}\n\n{text_content}"
            html_result = f"<h{section.level}>{section.title}</h{section.level}>\n{html_content}"

        return text_result, html_result

    def _format_abbreviations(self, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование списка сокращений (сортировка по алфавиту) - п. 2.2"""
        soup = BeautifulSoup(content, 'html.parser')

        abbreviations = []

        # Извлекаем аббревиатуры из разных структур
        for p in soup.find_all('p'):
            text = p.get_text().strip()
            if text and ('–' in text or '-' in text or '—' in text):
                abbreviations.append(text)

        for li in soup.find_all('li'):
            text = li.get_text().strip()
            if text:
                abbreviations.append(text)

        # Если ничего не нашли, пробуем извлечь весь текст построчно
        if not abbreviations:
            text_lines = soup.get_text().strip().split('\n')
            abbreviations = [line.strip() for line in text_lines if line.strip()]

        # Сортируем по алфавиту (п. 2.2)
        abbreviations.sort(key=lambda x: x.lower())

        # Форматируем как маркированный список
        text_result = '\n'.join([f"• {abbr}" for abbr in abbreviations])
        html_result = '<ul>\n' + '\n'.join([f"<li>{abbr}</li>" for abbr in abbreviations]) + '\n</ul>'

        return text_result, html_result

    def _format_terms(self, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование терминов и определений (с учетом вложенности) - п. 2.3"""
        soup = BeautifulSoup(content, 'html.parser')

        terms = []

        # Ищем определения в разных структурах
        for element in soup.find_all(['p', 'li', 'div']):
            text = element.get_text().strip()
            if text:
                # Проверяем, содержит ли определение (обычно через тире или двоеточие)
                if '–' in text or '—' in text or ':' in text:
                    terms.append(text)
                # Или если начинается с жирного текста
                elif element.find('strong') or element.find('b'):
                    terms.append(text)

        # Если не нашли структурированные термины, берем все параграфы
        if not terms:
            for p in soup.find_all('p'):
                text = p.get_text().strip()
                if text:
                    terms.append(text)

        # Сортируем по алфавиту
        terms.sort(key=lambda x: x.lower())

        # Форматируем с учетом возможной вложенности
        text_result = '\n\n'.join([f"• {term}" for term in terms])
        html_result = '<ul>\n' + '\n'.join([f"<li>{term}</li>" for term in terms]) + '\n</ul>'

        return text_result, html_result

    def _format_diagnosis(self, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование раздела диагностики - п. 2.4"""
        # Просто возвращаем контент как есть, БЕЗ создания дополнительного спойлера
        # Спойлер "Критерии диагностики" создается только если есть явная структура
        return self._clean_html_to_text(content), content

    def _format_treatment(self, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование раздела лечения (деление на подпункты) - п. 2.5"""
        soup = BeautifulSoup(content, 'html.parser')

        # Разделяем на подпункты
        subsections = []
        current_header = None
        current_content = []
        current_html = []

        for element in soup.children:
            if isinstance(element, Tag):
                if element.name in ['h3', 'h4', 'h5']:
                    # Сохраняем предыдущий подраздел
                    if current_header:
                        subsections.append({
                            'header': current_header,
                            'content': ''.join(current_content),
                            'html': ''.join(current_html)
                        })

                    current_header = element.get_text().strip()
                    current_content = []
                    current_html = []
                else:
                    current_content.append(element.get_text())
                    current_html.append(str(element))

        # Добавляем последний подраздел
        if current_header:
            subsections.append({
                'header': current_header,
                'content': ''.join(current_content),
                'html': ''.join(current_html)
            })

        # Если подразделов нет, возвращаем весь контент
        if not subsections:
            return self._clean_html_to_text(content), html_content

        # Форматируем подразделы
        text_parts = []
        html_parts = []

        for sub in subsections:
            text_parts.append(f"{sub['header']}\n{sub['content'].strip()}")
            html_parts.append(f"<h4>{sub['header']}</h4>\n{sub['html']}")

        return '\n\n'.join(text_parts), '\n\n'.join(html_parts)

    def _format_literature(self, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование списка литературы (сохранение нумерации) - п. 2.7"""
        soup = BeautifulSoup(content, 'html.parser')

        literature_items = []

        # Извлекаем элементы списка
        for li in soup.find_all('li'):
            text = li.get_text().strip()
            if text:
                literature_items.append(text)

        # Если нумерованный список не найден, ищем в параграфах
        if not literature_items:
            for p in soup.find_all('p'):
                text = p.get_text().strip()
                if text and (text[0].isdigit() or text.startswith('[')):
                    literature_items.append(text)

        # Сохраняем нумерацию
        text_result = '\n'.join(literature_items)
        html_result = '<ol>\n' + '\n'.join([f"<li>{item}</li>" for item in literature_items]) + '\n</ol>'

        return text_result, html_result

    def _format_appendices(self, title: str, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование приложений - п. 2.8"""
        title_lower = title.lower()

        # А1 и А2 не нужны (п. 2.8) - но они уже отфильтрованы в _build_sections_tree
        if 'а1' in title_lower or 'а2' in title_lower or 'приложение а1' in title_lower or 'приложение а2' in title_lower:
            return "", ""

        # Для коротких приложений (<500 символов) возвращаем как есть
        text_content = self._clean_html_to_text(content)
        if len(text_content) <= 500:
            # Очищаем HTML от дубликатов заголовков
            soup = BeautifulSoup(html_content, 'html.parser')

            # Удаляем заголовки h1-h6 которые дублируют title
            for header in soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']):
                header_text = header.get_text().strip()
                if header_text == title or header_text in title or title in header_text:
                    header.decompose()

            cleaned_html = str(soup)
            cleaned_text = self._clean_html_to_text(cleaned_html)

            return cleaned_text, cleaned_html

        # Для длинных приложений - форматирование с заголовками
        # (теперь это будет обработано в _format_tree_section через _format_section_with_headers)
        return text_content, html_content

    def _format_additional_info(self, content: str, html_content: str) -> Tuple[str, str]:
        """Форматирование дополнительной информации (прятать в спойлеры) - п. 2.6"""
        soup = BeautifulSoup(content, 'html.parser')

        # Ищем подразделы
        subsections = []
        current_header = None
        current_content = []
        current_html = []

        for element in soup.children:
            if isinstance(element, Tag):
                if element.name in ['h3', 'h4', 'h5']:
                    if current_header:
                        subsections.append({
                            'header': current_header,
                            'content': ''.join(current_content),
                            'html': ''.join(current_html)
                        })
                    current_header = element.get_text().strip()
                    current_content = []
                    current_html = []
                else:
                    current_content.append(element.get_text())
                    current_html.append(str(element))

        if current_header:
            subsections.append({
                'header': current_header,
                'content': ''.join(current_content),
                'html': ''.join(current_html)
            })

        # Если есть подразделы, форматируем каждый в спойлер
        if subsections:
            text_parts = []
            html_parts = []
            for sub in subsections:
                text_parts.append(f"%{sub['header']}%\n%{sub['content'].strip()}%")
                html_parts.append(f'<details><summary>{sub["header"]}</summary>\n{sub["html"]}\n</details>')

            return '\n\n'.join(text_parts), '\n\n'.join(html_parts)

        # Если подразделов нет, возвращаем как есть
        return self._clean_html_to_text(content), html_content

    def _clean_html_to_text(self, html_content: str) -> str:
        """Очистка HTML и преобразование в текст"""
        soup = BeautifulSoup(html_content, 'html.parser')

        # Убираем скрипты и стили
        for tag in soup(['script', 'style']):
            tag.decompose()

        # Получаем текст
        text = soup.get_text(separator='\n')

        # Убираем лишние пробелы и переносы
        lines = [line.strip() for line in text.split('\n')]
        text = '\n'.join(line for line in lines if line)

        return text

    def extract_images_and_tables(self, html_content: str, base_path: str) -> Tuple[str, List[str], List[str]]:
        """
        Извлечение изображений и таблиц из HTML
        Returns: (modified_html, image_files, table_files)
        """
        soup = BeautifulSoup(html_content, 'html.parser')
        image_files = []
        table_files = []

        # Обработка изображений
        images = soup.find_all('img')
        for img in images:
            src = img.get('src', '')

            # Обработка base64 изображений
            if src.startswith('data:image'):
                import base64
                match = re.match(r'data:image/(\w+);base64,(.+)', src)
                if match:
                    img_format, data = match.groups()
                    filename = f"image_{hashlib.md5(data.encode()).hexdigest()[:8]}.{img_format}"
                    filepath = os.path.join(base_path, filename)

                    try:
                        with open(filepath, 'wb') as f:
                            f.write(base64.b64decode(data))
                        image_files.append(filename)
                        img['src'] = filename
                    except Exception as e:
                        logger.error(f"Ошибка сохранения base64 изображения: {e}")

            # Обработка внешних URL
            elif src.startswith('http'):
                filename = f"image_{hashlib.md5(src.encode()).hexdigest()[:8]}.jpg"
                filepath = os.path.join(base_path, filename)

                if self._download_image(src, filepath):
                    image_files.append(filename)
                    img['src'] = filename

        # Обработка таблиц (п. 2.9)
        tables = soup.find_all('table')
        for idx, table in enumerate(tables):
            # Сохраняем таблицу как HTML файл
            table_filename = f"table_{idx + 1}.html"
            table_filepath = os.path.join(base_path, table_filename)

            try:
                with open(table_filepath, 'w', encoding='utf-8') as f:
                    f.write(str(table))
                table_files.append(table_filename)
                logger.info(f"Таблица сохранена: {table_filename}")
            except Exception as e:
                logger.error(f"Ошибка сохранения таблицы: {e}")

        return str(soup), image_files, table_files

    def _download_image(self, url: str, filepath: str) -> bool:
        """Скачать изображение по URL"""
        try:
            response = requests.get(url, timeout=10)
            response.raise_for_status()

            with open(filepath, 'wb') as f:
                f.write(response.content)

            return True
        except Exception as e:
            logger.error(f"Ошибка скачивания изображения {url}: {e}")
            return False
