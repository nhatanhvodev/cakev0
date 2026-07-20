# Stub — Task 15 replaces with full order_status/order_create action logic.
def action_node(deps, state):
    if state.get("intent") == "order_create":
        from app.engines.multiagent.order_create import order_create_node
        return order_create_node(deps, state)
    return {"response": "Bạn muốn đặt bánh nào ạ?"}
