from fastapi import APIRouter, Request, Response, Depends
from app.config import get_settings
from app.channels import messenger as ms
from app import deps as deps_mod
from app.db import chat_repo

router = APIRouter()


@router.get("/channels/messenger/webhook")
def verify(request: Request):
    q = request.query_params
    if q.get("hub.verify_token") == get_settings().fb_verify_token:
        return Response(q.get("hub.challenge", ""), media_type="text/plain")
    return Response(status_code=403)


@router.post("/channels/messenger/webhook")
async def receive(request: Request, engine=Depends(deps_mod.get_engine)):
    s = get_settings()
    body = await request.body()
    if not ms.verify_signature(s.fb_app_secret, body, request.headers.get("X-Hub-Signature-256")):
        return Response(status_code=403)
    for ev in ms.extract_events(await request.json()):
        conn = engine.deps.conn_factory()
        if conn is None:
            continue
        session = chat_repo.get_or_create_session(conn, source="messenger", external_user_id=ev["psid"])
        # tái dùng session cũ theo PSID nếu có
        history = [{"sender": m["sender"], "content": m["content"]}
                   for m in chat_repo.get_messages(conn, session["id"])]
        chat_repo.append_message(conn, session["id"], "customer", ev["text"])
        reply = engine.handle(history, ev["text"], {"session": session, "user_id": None})
        chat_repo.append_message(conn, session["id"], "bot", reply.content)
        conn.close()
        ms.send_text(s.fb_page_token, ev["psid"], reply.content)
        if reply.products:
            ms.send_payload(s.fb_page_token, ev["psid"], ms.build_generic_template(reply.products, s.site_base_url))
    return {"status": "ok"}
