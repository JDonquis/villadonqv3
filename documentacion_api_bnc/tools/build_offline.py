#!/usr/bin/env python3
"""Genera una copia HTML estatica y offline de las colecciones de Postman.

Uso: python3 tools/build_offline.py
"""

from __future__ import annotations

import html
import itertools
import json
import re
import unicodedata
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "data"

IMAGE_MAP = {
    "https://content.pstmn.io/d9b86224-aae8-4127-8fe3-1ccaac37f4d9/YXBpMS5qcGc=": "assets/images/api1.jpg",
}

MONTHS = {
    1: "enero",
    2: "febrero",
    3: "marzo",
    4: "abril",
    5: "mayo",
    6: "junio",
    7: "julio",
    8: "agosto",
    9: "septiembre",
    10: "octubre",
    11: "noviembre",
    12: "diciembre",
}

CODE_COUNTER = itertools.count(1)

TODAY = date.today()
DATE_LABEL = f"{TODAY.day} de {MONTHS[TODAY.month]} de {TODAY.year}"


def esc(value) -> str:
    return html.escape("" if value is None else str(value), quote=True)


def slugify(value: str, fallback: str = "seccion") -> str:
    value = unicodedata.normalize("NFKD", value or "")
    value = "".join(ch for ch in value if not unicodedata.combining(ch))
    value = value.lower()
    value = re.sub(r"[^a-z0-9]+", "-", value).strip("-")
    return value or fallback


class Slugger:
    def __init__(self) -> None:
        self.seen: dict[str, int] = {}

    def get(self, value: str) -> str:
        base = slugify(value)
        self.seen[base] = self.seen.get(base, 0) + 1
        return base if self.seen[base] == 1 else f"{base}-{self.seen[base]}"


def strip_tags(value: str) -> str:
    value = re.sub(r"<[^>]+>", " ", value or "")
    return re.sub(r"\s+", " ", html.unescape(value)).strip()


def clean_description(description: str | None, id_prefix: str = "") -> str:
    if not description:
        return ""

    match = re.search(r"<body[^>]*>(.*?)</body>", description, re.IGNORECASE | re.DOTALL)
    if match:
        description = match.group(1)
    else:
        description = re.sub(
            r"</?(?:html|head|body)[^>]*>", "", description, flags=re.IGNORECASE
        )

    description = re.sub(
        r"<script\b.*?</script>", "", description, flags=re.IGNORECASE | re.DOTALL
    )
    for remote, local in IMAGE_MAP.items():
        description = description.replace(remote, local)

    def add_link_target(match: re.Match) -> str:
        attrs, href = match.group(1), match.group(2)
        if "target=" not in attrs:
            attrs += ' target="_blank" rel="noopener noreferrer"'
        return f'<a{attrs}href="{href}"'

    description = re.sub(
        r'<a([^>]*?)href="(https?://[^"]+)"',
        add_link_target,
        description,
        flags=re.IGNORECASE,
    )

    if id_prefix:
        seen: dict[str, str] = {}

        def prefix_id(match: re.Match) -> str:
            original = match.group(1)
            if original not in seen:
                seen[original] = f"{id_prefix}-{original}"
            return f'id="{seen[original]}"'

        description = re.sub(r'\bid="([^"]+)"', prefix_id, description)

    return description.strip()


def extract_toc(fragment: str, levels: tuple[int, ...] = (1, 2, 3, 4)) -> list[dict]:
    toc = []
    for match in re.finditer(
        r"<h([1-6])\b([^>]*)>(.*?)</h\1>", fragment or "", re.IGNORECASE | re.DOTALL
    ):
        level = int(match.group(1))
        if level not in levels:
            continue
        attrs, inner = match.group(2), match.group(3)
        id_match = re.search(r'\bid="([^"]+)"', attrs)
        if not id_match:
            continue
        text = strip_tags(inner)
        if text:
            toc.append({"level": level, "id": id_match.group(1), "text": text})
    return toc


def render_toc(toc: list[dict], title: str = "Contenido") -> str:
    if not toc:
        return ""
    links = "\n".join(
        f'<a class="toc-link toc-level-{item["level"]}" href="#{esc(item["id"])}">'
        f'{esc(item["text"])}</a>'
        for item in toc
    )
    return f"""
    <nav class="toc-card" aria-label="{esc(title)}">
      <div class="toc-title">{esc(title)}</div>
      {links}
    </nav>"""


def method_class(method: str) -> str:
    method = (method or "GET").lower()
    return method if method in {"get", "post", "put", "patch", "delete", "head", "options"} else "other"


def url_of(request: dict) -> str:
    url = request.get("url")
    if isinstance(url, str):
        return url
    if isinstance(url, dict):
        return url.get("raw") or ""
    return ""


def render_headers(
    headers: list | None, empty_text: str = "Sin cabeceras", id_prefix: str = ""
) -> str:
    headers = [h for h in (headers or []) if isinstance(h, dict)]
    if not headers:
        return f'<p class="muted">{esc(empty_text)}</p>'
    rows = []
    for header in headers:
        disabled = " (deshabilitada)" if header.get("disabled") else ""
        desc = header.get("description")
        desc_html = (
            f'<div class="header-desc">{clean_description(desc, id_prefix)}</div>'
            if desc
            else ""
        )
        rows.append(
            "<tr>"
            f'<td><code>{esc(header.get("key", ""))}</code>{esc(disabled)}</td>'
            f'<td><code>{esc(header.get("value", ""))}</code>{desc_html}</td>'
            "</tr>"
        )
    return (
        '<div class="table-wrap"><table class="data-table compact">'
        "<thead><tr><th>Cabecera</th><th>Valor</th></tr></thead>"
        f"<tbody>{''.join(rows)}</tbody></table></div>"
    )


def render_params(params: list | None, key_label: str = "Parámetro") -> str:
    params = [p for p in (params or []) if isinstance(p, dict)]
    if not params:
        return ""
    rows = []
    for param in params:
        required = "Sí" if param.get("required") else "No"
        desc = strip_tags(clean_description(param.get("description")))
        rows.append(
            "<tr>"
            f'<td><code>{esc(param.get("key", ""))}</code></td>'
            f'<td><code>{esc(param.get("value", ""))}</code></td>'
            f"<td>{esc(required)}</td>"
            f"<td>{esc(desc)}</td>"
            "</tr>"
        )
    return (
        '<div class="table-wrap"><table class="data-table compact">'
        f"<thead><tr><th>{esc(key_label)}</th><th>Valor</th><th>Requerido</th><th>Descripción</th></tr></thead>"
        f"<tbody>{''.join(rows)}</tbody></table></div>"
    )


def render_body(body: dict | None) -> str:
    if not body:
        return '<p class="muted">Sin cuerpo de petición.</p>'

    mode = body.get("mode")
    if mode == "raw":
        raw = body.get("raw", "")
        language = ((body.get("options") or {}).get("raw") or {}).get("language", "")
        label = f"Raw ({language})" if language else "Raw"
        return render_code(raw, label)
    if mode == "urlencoded":
        return render_params(body.get("urlencoded"), "Campo")
    if mode == "formdata":
        return render_params(body.get("formdata"), "Campo")
    if mode == "graphql":
        graphql = body.get("graphql") or {}
        content = json.dumps(graphql, ensure_ascii=False, indent=2)
        return render_code(content, "GraphQL")
    if mode == "file":
        file_info = body.get("file") or {}
        return render_code(json.dumps(file_info, ensure_ascii=False, indent=2), "Archivo")
    return render_code(json.dumps(body, ensure_ascii=False, indent=2), "Body")


def render_code(content: str, label: str = "", language: str = "") -> str:
    block_id = f"code-{next(CODE_COUNTER)}"
    lang_class = f" language-{slugify(language)}" if language else ""
    label_html = f'<div class="code-label">{esc(label)}</div>' if label else ""
    return f"""
    <div class="code-block">
      {label_html}
      <button type="button" class="copy-btn" data-copy="{block_id}">Copiar</button>
      <pre class="code"><code id="{block_id}" class="{lang_class.strip()}">{html.escape(content or "")}</code></pre>
    </div>"""


def render_auth(auth) -> str:
    if not auth:
        return ""
    return render_code(json.dumps(auth, ensure_ascii=False, indent=2), "Autenticación")


def render_response(response: dict, index: int, scope: str = "respuesta") -> str:
    name = response.get("name") or f"Ejemplo de respuesta {index}"
    code = response.get("code")
    status = response.get("status")
    status_html = ""
    if code or status:
        code_class = "ok" if code and 200 <= int(code) < 300 else "warn"
        status_html = (
            f'<span class="status-code {code_class}">{esc(code or "")}</span>'
            f'<span class="status-text">{esc(status or "")}</span>'
        )

    time_html = ""
    if response.get("responseTime") is not None:
        time_html = f'<span class="chip">{esc(response["responseTime"])} ms</span>'

    preview = response.get("_postman_previewlanguage") or ""
    response_body = response.get("body")
    if response_body is None or response_body == "":
        body_html = '<p class="muted">Sin cuerpo de respuesta.</p>'
    else:
        body_html = render_code(response_body, preview.upper() if preview else "Respuesta", preview)

    cookies = [c for c in (response.get("cookie") or []) if isinstance(c, dict)]
    cookies_html = ""
    if cookies:
        rows = "".join(
            f"<tr><td><code>{esc(c.get('name', ''))}</code></td>"
            f"<td><code>{esc(c.get('value', ''))}</code></td></tr>"
            for c in cookies
        )
        cookies_html = (
            '<h5>Cookies</h5><div class="table-wrap"><table class="data-table compact">'
            "<thead><tr><th>Nombre</th><th>Valor</th></tr></thead>"
            f"<tbody>{rows}</tbody></table></div>"
        )

    return f"""
    <div class="response">
      <div class="response-head">
        <h4>{esc(name)}</h4>
        <div class="response-meta">{status_html}{time_html}</div>
      </div>
      <h5>Cabeceras de respuesta</h5>
      {render_headers(response.get("header"), "Sin cabeceras de respuesta", f"{scope}-{index}")}
      {cookies_html}
      <h5>Cuerpo de respuesta</h5>
      {body_html}
    </div>"""


def render_request(item: dict, request_id: str, group_slug: str) -> tuple[str, str]:
    request = item.get("request") or {}
    name = item.get("name") or "Petición sin nombre"
    method = (request.get("method") or "GET").upper()
    url = url_of(request)
    description = clean_description(request.get("description"), request_id)

    body = request.get("body") or {}
    headers = request.get("header") or []
    url_object = request.get("urlObject") or {}
    query = url_object.get("query") if isinstance(url_object, dict) else None
    path_variables = url_object.get("variable") if isinstance(url_object, dict) else None

    query_html = render_params(query, "Query param") if query else ""
    variables_html = render_params(path_variables, "Variable de ruta") if path_variables else ""
    auth_html = render_auth(request.get("auth"))

    responses = [r for r in (item.get("response") or []) if isinstance(r, dict)]
    responses_html = (
        "".join(
            render_response(response, i, request_id)
            for i, response in enumerate(responses, 1)
        )
        if responses
        else '<p class="muted">La colección no incluye ejemplos de respuesta para esta petición.</p>'
    )

    detail = f"""
    <details class="request-details" open>
      <summary>Detalles de la petición</summary>
      <h5>Cabeceras</h5>
      {render_headers(headers, id_prefix=request_id)}
      {f'<h5>Query params</h5>{query_html}' if query_html else ''}
      {f'<h5>Variables de ruta</h5>{variables_html}' if variables_html else ''}
      {f'<h5>Autenticación</h5>{auth_html}' if auth_html else ''}
      <h5>Cuerpo</h5>
      {render_body(body)}
    </details>"""

    article = f"""
    <article class="endpoint" id="{request_id}" data-spy>
      <header class="endpoint-head">
        <span class="method {method_class(method)}">{esc(method)}</span>
        <h3>{esc(name)}</h3>
      </header>
      <div class="url-bar">
        <code>{esc(url)}</code>
        <button type="button" class="copy-btn small" data-copy="{request_id}-url">Copiar URL</button>
        <span id="{request_id}-url" hidden>{esc(url)}</span>
      </div>
      {f'<div class="description">{description}</div>' if description else ''}
      {detail}
      <h4 class="responses-title">Respuestas de ejemplo</h4>
      {responses_html}
    </article>"""

    search_text = f"{name} {method} {url}"
    nav = f"""
      <a class="nav-link nav-request" href="#{request_id}" data-search="{esc(search_text)}">
        <span class="mini-method {method_class(method)}">{esc(method)}</span>
        <span>{esc(name)}</span>
      </a>"""
    return article, nav


def build_items(items: list, slugger: Slugger, parent_level: int = 0) -> tuple[str, str, int]:
    articles: list[str] = []
    nav_links: list[str] = []
    endpoint_count = 0

    for item in items or []:
        if not isinstance(item, dict):
            continue
        name = item.get("name") or "Sección"
        if "item" in item:
            group_id = f"grupo-{slugger.get(name)}"
            group_title = f'<span class="group-name">{esc(name)}</span>'
            description = clean_description(item.get("description"), group_id)
            child_articles, child_nav, child_count = build_items(
                item.get("item") or [], slugger, parent_level + 1
            )
            endpoint_count += child_count
            group_desc_html = (
                f'<div class="description group-description">{description}</div>'
                if description
                else ""
            )
            articles.append(
                f"""
    <section class="group level-{parent_level + 1}" id="{group_id}" data-spy>
      <div class="group-head">
        <div class="eyebrow">Grupo</div>
        <h2>{esc(name)}</h2>
      </div>
      {group_desc_html}
      {child_articles}
    </section>"""
            )
            nav_links.append(
                f"""
      <div class="nav-group" data-nav-group>
        <a class="nav-link nav-group-title level-{parent_level + 1}" href="#{group_id}" data-search="{esc(name)}">
          {group_title}
        </a>
        <div class="nav-children">
          {child_nav}
        </div>
      </div>"""
            )
        else:
            request_id = f"peticion-{slugger.get(name)}"
            article, nav = render_request(item, request_id, "")
            articles.append(article)
            nav_links.append(nav)
            endpoint_count += 1

    return "\n".join(articles), "\n".join(nav_links), endpoint_count


def count_requests(items: list) -> int:
    total = 0
    for item in items or []:
        if not isinstance(item, dict):
            continue
        if "item" in item:
            total += count_requests(item.get("item") or [])
        else:
            total += 1
    return total


def count_groups(items: list) -> int:
    return sum(1 for item in (items or []) if isinstance(item, dict) and "item" in item)


def page_shell(
    *,
    filename: str,
    page_title: str,
    active: str,
    sidebar_html: str,
    main_html: str,
    has_search: bool = True,
) -> str:
    search_html = ""
    if has_search:
        search_html = """
        <div class="search-box">
          <label for="nav-search" class="sr-only">Buscar en la documentación</label>
          <input id="nav-search" type="search" placeholder="Buscar endpoint o sección..." autocomplete="off">
          <span class="search-hint">Filtra la navegación</span>
        </div>"""

    return f"""<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{esc(page_title)}</title>
  <meta name="description" content="Copia offline de la documentación de las APIs del Banco Nacional de Crédito.">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <a class="skip-link" href="#contenido">Saltar al contenido</a>

  <header class="topbar">
    <button class="menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="sidebar">
      <span></span><span></span><span></span>
    </button>
    <a class="topbar-brand" href="index.html">
      <img src="assets/images/bnc-logo.png" alt="BNC">
      <span>Documentación API</span>
    </a>
    <nav class="topbar-nav" aria-label="Documentos">
      <a href="esolutions-api.html" class="{'active' if active == 'esolutions' else ''}">ESolutions API</a>
      <a href="notification-push.html" class="{'active' if active == 'notification' else ''}">NotificationPush</a>
    </nav>
  </header>

  <div class="layout">
    <aside class="sidebar" id="sidebar">
      {search_html}
      <nav class="side-nav" aria-label="Contenido del documento">
        <div class="nav-section-label">Documento</div>
        <a class="nav-link nav-home" href="index.html">
          <span class="home-icon">←</span> Todas las colecciones
        </a>
        {sidebar_html}
      </nav>
      <div class="sidebar-footer">
        <a href="data/{esc(filename)}" target="_blank" rel="noopener">Ver JSON de la colección</a>
        <span>Copia offline · {esc(DATE_LABEL)}</span>
      </div>
    </aside>

    <div class="sidebar-overlay" hidden></div>

    <main class="main" id="contenido">
      {main_html}
      <footer class="page-footer">
        <p>
          Copia offline generada el {esc(DATE_LABEL)}. El contenido proviene de la documentación pública
          de Postman del Banco Nacional de Crédito.
        </p>
        <p>Los enlaces a la API y a la documentación original siguen apuntando a sus sitios web.</p>
      </footer>
    </main>
  </div>

  <button class="to-top" type="button" aria-label="Volver arriba" title="Volver arriba">↑</button>
  <script src="assets/app.js"></script>
</body>
</html>
"""


def render_esolutions(collection: dict, metadata: dict) -> str:
    info = collection.get("info") or {}
    items = collection.get("item") or []
    title = info.get("name") or "ESolutions API"
    description = clean_description(info.get("description"), "doc")
    toc = extract_toc(description, levels=(1, 2, 3, 4))
    slugger = Slugger()

    articles, nav_links, endpoint_count = build_items(items, slugger)
    groups = count_groups(items)

    original_url = "https://documenter.getpostman.com/view/37111270/2sAYBRFtg4"
    raw_json = "esolutions.collection.json"

    toc_sidebar = "".join(
        f'<a class="nav-link nav-toc level-{item["level"]}" href="#{esc(item["id"])}" '
        f'data-search="{esc(item["text"])}">{esc(item["text"])}</a>'
        for item in toc
    )

    sidebar = f"""
    <a class="nav-link nav-intro" href="#resumen" data-search="Resumen introducción">Resumen</a>
    <div class="nav-group" data-nav-group>
      <div class="nav-section-label collapsible-label">Guía</div>
      <div class="nav-children">{toc_sidebar}</div>
    </div>
    <div class="nav-section-label">Endpoints</div>
    {nav_links}
    """

    main = f"""
    <section class="hero" id="resumen" data-spy>
      <div class="hero-top">
        <img class="hero-logo" src="assets/images/bnc-logo.png" alt="Banco Nacional de Crédito">
        <div class="hero-badges">
          <span class="chip strong">{endpoint_count} endpoints</span>
          <span class="chip">{groups} grupos</span>
          <span class="chip">Snapshot {esc(DATE_LABEL)}</span>
        </div>
      </div>
      <div class="eyebrow">Colección Postman · API REST</div>
      <h1>{esc(title)}</h1>
      <p class="hero-lead">
        Documentación completa de integración: autenticación, pagos P2P y C2P, VPOS,
        consultas, validaciones, gestor de asociados, crédito y débito inmediato.
      </p>
      <div class="hero-actions">
        <a class="button primary" href="{esc(original_url)}" target="_blank" rel="noopener">Documentación original ↗</a>
        <a class="button" href="data/{esc(raw_json)}" target="_blank" rel="noopener">Colección JSON</a>
        <a class="button" href="data/esolutions.metadata.json" target="_blank" rel="noopener">Metadatos</a>
      </div>
    </section>

    <section class="intro">
      <div class="content-grid">
        <article class="prose description">
          {description}
        </article>
        {render_toc(toc, "En esta guía")}
      </div>
    </section>

    {articles}
    """

    return page_shell(
        filename=raw_json,
        page_title=title,
        active="esolutions",
        sidebar_html=sidebar,
        main_html=main,
    )


def render_notification(collection: dict, metadata: dict) -> str:
    info = collection.get("info") or {}
    title = info.get("name") or "NotificationPush"
    description = clean_description(info.get("description"), "doc")
    toc = extract_toc(description, levels=(1, 2, 3, 4))

    original_url = "https://documenter.getpostman.com/view/48256705/2sB3QDuCXT"
    raw_json = "notification-push.collection.json"

    toc_sidebar = "".join(
        f'<a class="nav-link nav-toc level-{item["level"]}" href="#{esc(item["id"])}" '
        f'data-search="{esc(item["text"])}">{esc(item["text"])}</a>'
        for item in toc
    )

    sidebar = f"""
    <a class="nav-link nav-intro" href="#resumen" data-search="Resumen introducción">Resumen</a>
    <div class="nav-section-label">Contenido</div>
    {toc_sidebar}
    """

    main = f"""
    <section class="hero" id="resumen" data-spy>
      <div class="hero-top">
        <img class="hero-logo square" src="assets/images/notification-logo.png" alt="NotificationPush">
        <div class="hero-badges">
          <span class="chip strong">Documento técnico</span>
          <span class="chip">Notificaciones Push</span>
          <span class="chip">Snapshot {esc(DATE_LABEL)}</span>
        </div>
      </div>
      <div class="eyebrow">Colección Postman · Documento de integración</div>
      <h1>{esc(title)}</h1>
      <p class="hero-lead">
        Reglas de recepción y respuesta de notificaciones, disponibilidad del servicio,
        autenticación, validaciones, certificación y requisitos de integración.
      </p>
      <div class="hero-actions">
        <a class="button primary" href="{esc(original_url)}" target="_blank" rel="noopener">Documentación original ↗</a>
        <a class="button" href="data/{esc(raw_json)}" target="_blank" rel="noopener">Colección JSON</a>
        <a class="button" href="data/notification-push.metadata.json" target="_blank" rel="noopener">Metadatos</a>
      </div>
    </section>

    <section class="intro">
      <div class="content-grid">
        <article class="prose description">
          {description}
        </article>
        {render_toc(toc, "Contenido del documento")}
      </div>
    </section>
    """

    return page_shell(
        filename=raw_json,
        page_title=title,
        active="notification",
        sidebar_html=sidebar,
        main_html=main,
    )


def render_index(esolutions: dict, notification: dict) -> str:
    es_info = esolutions.get("info") or {}
    notif_info = notification.get("info") or {}
    es_items = esolutions.get("item") or []
    endpoints = count_requests(es_items)
    groups = count_groups(es_items)

    return f"""<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentación API BNC · Copia offline</title>
  <meta name="description" content="Copia offline de la documentación de las APIs del Banco Nacional de Crédito.">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="landing">
  <a class="skip-link" href="#contenido">Saltar al contenido</a>

  <header class="topbar">
    <a class="topbar-brand" href="index.html">
      <img src="assets/images/bnc-logo.png" alt="BNC">
      <span>Documentación API</span>
    </a>
    <nav class="topbar-nav" aria-label="Documentos">
      <a href="esolutions-api.html">ESolutions API</a>
      <a href="notification-push.html">NotificationPush</a>
    </nav>
  </header>

  <main class="landing-main" id="contenido">
    <div class="eyebrow">Banco Nacional de Crédito</div>
    <h1>Documentación API BNC</h1>
    <p class="landing-lead">
      Copia local y navegable de las dos documentaciones públicas de Postman.
      Todo el contenido está incluido: descripciones, endpoints, cuerpos de petición,
      respuestas de ejemplo, imágenes y datos originales en JSON.
    </p>
    <p class="snapshot">Snapshot generado el {esc(DATE_LABEL)}.</p>

    <div class="doc-grid">
      <a class="doc-card" href="esolutions-api.html">
        <div class="doc-card-top">
          <img src="assets/images/bnc-logo.png" alt="BNC">
          <span class="chip strong">{endpoints} endpoints</span>
        </div>
        <h2>{esc(es_info.get("name") or "ESolutions API")}</h2>
        <p>
          Integración REST: autenticación, P2P, C2P, VPOS, consultas, validaciones,
          gestor de asociados y operaciones SIMF de crédito y débito inmediato.
        </p>
        <div class="doc-card-meta">{groups} grupos · Documentación completa</div>
        <span class="doc-card-action">Abrir documentación →</span>
      </a>

      <a class="doc-card" href="notification-push.html">
        <div class="doc-card-top">
          <img class="square" src="assets/images/notification-logo.png" alt="NotificationPush">
          <span class="chip strong">Documento técnico</span>
        </div>
        <h2>{esc(notif_info.get("name") or "BNC NotificationPush V2.0")}</h2>
        <p>
          Reglas de recepción y respuesta, disponibilidad, autenticación API Key/JWT,
          validaciones, endpoint de ping, certificación y ambientes de integración.
        </p>
        <div class="doc-card-meta">Guía de integración · Sin colección de requests</div>
        <span class="doc-card-action">Abrir documentación →</span>
      </a>
    </div>

    <section class="landing-info">
      <h2>Contenido incluido</h2>
      <div class="info-grid">
        <div>
          <h3>HTML offline</h3>
          <p>Las dos páginas funcionan sin conexión y conservan navegación, búsqueda y ejemplos.</p>
        </div>
        <div>
          <h3>Datos originales</h3>
          <p>Colecciones y metadatos de Postman guardados en <code>data/</code> en formato JSON.</p>
        </div>
        <div>
          <h3>Recursos locales</h3>
          <p>Logos e imágenes descargados en <code>assets/images/</code>. No se cargan librerías externas.</p>
        </div>
      </div>
    </section>

    <section class="landing-sources">
      <h2>Fuentes originales</h2>
      <ul>
        <li><a href="https://documenter.getpostman.com/view/37111270/2sAYBRFtg4" target="_blank" rel="noopener">ESolutions API Documentation v4.1</a></li>
        <li><a href="https://documenter.getpostman.com/view/48256705/2sB3QDuCXT" target="_blank" rel="noopener">BNC NotificationPush V2.0</a></li>
      </ul>
    </section>
  </main>

  <footer class="page-footer landing-footer">
    <p>Contenido propiedad del Banco Nacional de Crédito. Copia generada para consulta offline.</p>
  </footer>
</body>
</html>
"""


def main() -> None:
    esolutions = json.loads((DATA / "esolutions.collection.json").read_text(encoding="utf-8"))
    es_meta = json.loads((DATA / "esolutions.metadata.json").read_text(encoding="utf-8"))
    notification = json.loads(
        (DATA / "notification-push.collection.json").read_text(encoding="utf-8")
    )
    notif_meta = json.loads(
        (DATA / "notification-push.metadata.json").read_text(encoding="utf-8")
    )

    (ROOT / "index.html").write_text(
        render_index(esolutions, notification), encoding="utf-8"
    )
    (ROOT / "esolutions-api.html").write_text(
        render_esolutions(esolutions, es_meta), encoding="utf-8"
    )
    (ROOT / "notification-push.html").write_text(
        render_notification(notification, notif_meta), encoding="utf-8"
    )

    print("Generado:")
    print(f"  {ROOT / 'index.html'}")
    print(f"  {ROOT / 'esolutions-api.html'}")
    print(f"  {ROOT / 'notification-push.html'}")


if __name__ == "__main__":
    main()
