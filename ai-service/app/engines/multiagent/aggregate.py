_HANDOFF_MARKERS = ("nhân viên", "hỗ trợ viên", "quản lý", "hotline")


def aggregate_node(deps, state):
    resp = state.get("response", "")
    if state.get("should_handoff") and not any(m in resp.lower() for m in _HANDOFF_MARKERS):
        resp = (resp + "\n\nMình đã chuyển cuộc hội thoại đến nhân viên hỗ trợ, bạn chờ chút nhé.").strip()
    return {"response": resp}
