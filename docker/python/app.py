from flask import Flask, request, jsonify, Response
from functools import lru_cache
import re
import pymorphy3

app = Flask(__name__)
morph = pymorphy3.MorphAnalyzer()
token_re = re.compile(r"\w+", re.UNICODE)
split_re = re.compile(r"(\w+)", re.UNICODE)

@lru_cache(maxsize=100_000) # кэш
def parse_word(word_cf: str):
    p = morph.parse(word_cf)[0]
    return p.normal_form

@app.post("/lemmatize")
def lemmatize():
    data = request.get_json(silent=True) or {}
    text = (data.get("text") or "").strip()
    if not text:
        return jsonify({"error": "text is required"}), 400

    parts = split_re.split(text)
    for i, part in enumerate(parts):
        if part and part.isidentifier():
            parts[i] = parse_word(part.casefold())
    return Response("".join(parts), mimetype="text/plain")
