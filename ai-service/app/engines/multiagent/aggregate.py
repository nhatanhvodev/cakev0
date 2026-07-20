def aggregate_node(deps, state):
    resp = state.get("response", "")
    if state.get("should_handoff") and "nhân viên" not in resp:
        resp = (resp + "\n\nMình đã chuyển cuộc hội thoại đến nhân viên hỗ trợ, bạn chờ chút nhé.").strip()
    return {"response": resp}
