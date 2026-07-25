import threading
import urllib.request
import urllib.parse
import json

from app.config import Settings


def _send_telegram(token: str, chat_id: str, text: str):
    url = f"https://api.telegram.org/bot{token}/sendMessage"
    data = urllib.parse.urlencode({"chat_id": chat_id, "text": text, "parse_mode": "HTML"}).encode()
    try:
        urllib.request.urlopen(url, data, timeout=5)
    except Exception:
        pass


def notify_handoff(settings: Settings, session_id, query: str, reasons: list[str], priority: str):
    token = settings.telegram_bot_token
    chat_id = settings.telegram_chat_id
    if not token or not chat_id:
        return
    reason_str = ", ".join(reasons)
    text = (
        f"<b>🔔 Handoff Alert</b>\n"
        f"Session: #{session_id}\n"
        f"Priority: {priority}\n"
        f"Reasons: {reason_str}\n"
        f"Customer: {query[:200]}"
    )
    threading.Thread(target=_send_telegram, args=(token, chat_id, text), daemon=True).start()
