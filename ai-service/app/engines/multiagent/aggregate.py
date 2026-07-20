# Stub — Task 15 replaces with full aggregation logic (multi-source merge,
# handoff-message composition, confidence blending).
def aggregate_node(deps, state):
    return {"response": state.get("response", "")}
