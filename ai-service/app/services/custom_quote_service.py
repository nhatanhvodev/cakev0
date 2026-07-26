"""Multi-turn slot-filling for custom-cake quote requests.

Collects the details staff need to call back with a price, then writes a lead
into contact_requests. Unlike order_create this does NOT require login — a guest
can request a quote (we collect name + phone in the flow).
"""

import json

from app.engines.multiagent.action import extract_phone
from app.services.order_create_service import is_confirmation

SKIP_WORDS = {"không", "khong", "ko", "bỏ qua", "bo qua", "skip", "không có", "khong co"}

# step -> (prompt shown when we ENTER this step, field to store the raw answer)
_STEPS = ["occasion", "servings", "flavor", "date", "note", "name", "phone", "confirm"]

_PROMPTS = {
    "occasion": "Bạn đặt bánh cho dịp gì ạ? (sinh nhật, cưới hỏi, thôi nôi, khai trương...)",
    "servings": "Bánh dùng cho khoảng bao nhiêu người ạ? (giúp mình tư vấn cỡ bánh)",
    "flavor": "Bạn muốn vị bánh gì? (chocolate, dâu, matcha, vani, trái cây...)",
    "date": "Bạn cần lấy bánh vào ngày nào ạ?",
    "note": "Bạn có yêu cầu trang trí, lời chúc hay ghi chú gì thêm không? "
            "(nhắn nội dung, hoặc 'không' để bỏ qua)",
    "name": "Cho mình xin tên người liên hệ nhé.",
    "phone": "Số điện thoại để nhân viên gọi báo giá là gì ạ?",
}


def _summary(draft: dict) -> str:
    note = draft.get("note") or "(không)"
    return ("Xác nhận yêu cầu đặt bánh riêng:\n"
            f"- Dịp: {draft['occasion']}\n"
            f"- Số người: {draft['servings']}\n"
            f"- Vị: {draft['flavor']}\n"
            f"- Ngày cần: {draft['date']}\n"
            f"- Ghi chú: {note}\n"
            f"- Người liên hệ: {draft['name']} — {draft['phone']}\n\n"
            "Bạn trả lời \"đồng ý\" để gửi yêu cầu, hoặc nhắn nội dung cần sửa.")


def _lead_message(draft: dict) -> str:
    note = draft.get("note") or "(không)"
    return ("[Yêu cầu báo giá bánh đặt riêng qua chat]\n"
            f"Dịp: {draft['occasion']}\n"
            f"Số người: {draft['servings']}\n"
            f"Vị: {draft['flavor']}\n"
            f"Ngày cần: {draft['date']}\n"
            f"Ghi chú: {note}")


def advance_quote(deps, session: dict, message: str) -> tuple[str, bool, dict]:
    """Returns (response, created, new_draft). new_draft={} clears the flow."""
    meta = session.get("metadata")
    meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
    draft = meta.get("custom_quote") or {"step": "occasion"}
    step = draft["step"]
    text = message.strip()
    low = text.lower()

    if step == "occasion":
        draft["occasion"] = text[:255]
        draft["step"] = "servings"
        return _PROMPTS["servings"], False, draft
    if step == "servings":
        draft["servings"] = text[:100]
        draft["step"] = "flavor"
        return _PROMPTS["flavor"], False, draft
    if step == "flavor":
        draft["flavor"] = text[:255]
        draft["step"] = "date"
        return _PROMPTS["date"], False, draft
    if step == "date":
        draft["date"] = text[:100]
        draft["step"] = "note"
        return _PROMPTS["note"], False, draft
    if step == "note":
        draft["note"] = "" if low in SKIP_WORDS else text[:500]
        draft["step"] = "name"
        return _PROMPTS["name"], False, draft
    if step == "name":
        draft["name"] = text[:255]
        draft["step"] = "phone"
        return _PROMPTS["phone"], False, draft
    if step == "phone":
        phone = extract_phone(text)
        if not phone:
            return ("Số điện thoại chưa hợp lệ, bạn nhập lại giúp mình (vd 0901234567).",
                    False, draft)
        draft["phone"] = phone
        draft["step"] = "confirm"
        return _summary(draft), False, draft
    if step == "confirm":
        if not is_confirmation(text):
            # treat the message as a correction to the notes, re-summarize
            draft["note"] = text[:500]
            return ("Mình đã ghi nhận chỉnh sửa. " + _summary(draft), False, draft)
        conn = deps.conn_factory()
        if conn is None:
            return ("Hệ thống đang bận, bạn gọi hotline 0901 234 567 để được báo giá nhanh nhé.",
                    False, draft)
        try:
            from app.db import features_repo
            features_repo.create_custom_quote_lead(conn, draft["name"], draft["phone"],
                                                   _lead_message(draft))
        finally:
            conn.close()
        return ("Đã ghi nhận yêu cầu đặt bánh riêng của bạn! 🎂 Nhân viên Gấu Bakery sẽ liên hệ "
                f"số {draft['phone']} để tư vấn và báo giá trong giờ làm việc 08:00–21:00. "
                "Cảm ơn bạn đã tin tưởng!"), True, {}
    return _PROMPTS["occasion"], False, {"step": "occasion"}
