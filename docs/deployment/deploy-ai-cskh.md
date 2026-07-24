# Triển khai AI CSKH thêm vào web đã deploy sẵn

Bối cảnh: web + DB đã chạy production.
- **Web PHP**: <https://cake-i8l0.onrender.com> (base path `/cakev0`)
- **DB**: Aiven MySQL, đã kết nối Workbench

Cần làm thêm để bật chatbot AI CSKH:
1. Áp migration chat tables lên Aiven.
2. Redeploy web PHP từ nhánh `feat/ai-cskh-agent` (widget + proxy + internal order API chưa có trên `main`).
3. Tạo Render service mới cho `ai-service` (Python FastAPI).
4. Nối 2 service qua env var + index knowledge.

> ⚠️ Chỉ deploy `ai-service` là **chưa đủ**. Code chat (widget JS, `api/chat/*.php`,
> `api/internal/orders/create.php`, admin inbox) nằm ở nhánh feat, không có trên
> `main` — web hiện tại chưa render nút chat. Bước 2 bắt buộc.

---

## 1. Áp migration chat tables lên Aiven (Workbench)

Mở `database/migrations/2026_07_19_create_chat_tables.sql` trong Workbench (File → Open SQL Script) → chọn connection Aiven → ⚡ Execute All.

Verify:
```sql
USE banh_store;
SHOW TABLES LIKE 'chat_%';       -- chat_messages, chat_sessions
SHOW TABLES LIKE 'faq_entries';
SHOW TABLES LIKE 'support_tickets';
```

FK trỏ vào `users(id)` + `admins(id)` — 2 bảng phải có sẵn (đã có từ `banh_store.sql`).

---

## 2. Redeploy web PHP từ nhánh feat

### Cách A — merge feat → main (khuyến nghị, autoDeploy sẽ tự chạy)

```bash
git checkout main
git merge feat/ai-cskh-agent
git push origin main
```

Render service web (`autoDeploy: true` trong `render.yaml`) tự build lại.

### Cách B — trỏ Render branch sang feat (không merge)

Render Dashboard → service web → **Settings** → **Build & Deploy** → **Branch** → đổi `main` thành `feat/ai-cskh-agent` → Save → **Manual Deploy → Deploy latest commit**.

### Thêm 2 env var vào web service

Render → web service → **Environment** → Add:

| Key | Value |
|---|---|
| `INTERNAL_API_SECRET` | `<sinh 1 chuỗi, xem dưới>` |
| `AI_SERVICE_URL` | `https://gau-bakery-ai.onrender.com` (điền sau khi tạo ở bước 3) |

Sinh secret (dùng chung web + ai-service):
```bash
python -c "import secrets; print(secrets.token_hex(32))"
```

Save → web restart.

---

## 3. Tạo Render service cho ai-service

### 3.1 Thêm block vào `render.yaml`

Nối vào cuối `render.yaml` (đã ở nhánh feat sau khi merge):

```yaml
  - type: web
    name: gau-bakery-ai
    runtime: docker
    plan: free
    region: singapore
    rootDir: ai-service
    dockerfilePath: ./Dockerfile
    autoDeploy: true
    healthCheckPath: /health
    envVars:
      - key: ENGINE
        value: multiagent
      - key: GEMINI_API_KEY
        sync: false
      - key: LLM_MODEL
        value: gemini-2.0-flash
      - key: EMBEDDING_MODEL
        value: text-embedding-004
      - key: LLM_TEMPERATURE
        value: "0.3"
      - key: CHROMA_PERSIST_DIR
        value: /app/data/chroma_db
      - key: MYSQL_HOST
        sync: false
      - key: MYSQL_PORT
        sync: false
      - key: MYSQL_USER
        sync: false
      - key: MYSQL_PASSWORD
        sync: false
      - key: MYSQL_DATABASE
        value: banh_store
      - key: MYSQL_SSL
        value: "true"
      - key: INTERNAL_API_SECRET
        sync: false
      - key: INTERNAL_ORDER_API_URL
        value: https://cake-i8l0.onrender.com/cakev0/api/internal/orders/create.php
      - key: HANDOFF_CONFIDENCE_THRESHOLD
        value: "0.6"
      - key: CORS_ORIGINS
        value: https://cake-i8l0.onrender.com
      - key: SITE_BASE_URL
        value: https://cake-i8l0.onrender.com/cakev0
      - key: ENABLE_NORMALIZER
        value: "true"
      - key: FB_PAGE_TOKEN
        sync: false
      - key: FB_VERIFY_TOKEN
        sync: false
      - key: FB_APP_SECRET
        sync: false
```

Commit + push:
```bash
git add render.yaml
git commit -m "chore: add ai-service to render blueprint"
git push origin main
```

### 3.2 Tạo service qua Blueprint

Render Dashboard → **New → Blueprint** → chọn repo → Render đọc `render.yaml` thấy service mới `gau-bakery-ai` → **Apply**.

Hoặc tạo tay: **New → Web Service** → connect repo → Root Directory `ai-service` → Runtime `Docker` → điền env như bảng trên.

### 3.3 Điền secrets (`sync: false`)

Service `gau-bakery-ai` → **Environment**:

| Key | Value |
|---|---|
| `GEMINI_API_KEY` | `AIzaSy...` (https://aistudio.google.com/app/apikey) |
| `MYSQL_HOST` | `gau-bakery-db-xxx.a.aivencloud.com` |
| `MYSQL_PORT` | `12345` (port Aiven, không phải 3306) |
| `MYSQL_USER` | `avnadmin` (hoặc user riêng) |
| `MYSQL_PASSWORD` | `<Aiven password>` |
| `INTERNAL_API_SECRET` | **cùng value** với web service ở bước 2 |

Save → service build + deploy.

### 3.4 Cập nhật `AI_SERVICE_URL` cho web

Sau khi `gau-bakery-ai` **Live**, copy URL của nó (vd `https://gau-bakery-ai.onrender.com`) → về web service → Environment → sửa `AI_SERVICE_URL` = URL đó → Save (web restart).

### 3.5 Persistent disk cho Chroma (khuyến nghị)

Free tier disk ephemeral → index mất mỗi restart. Add disk $1/mo:

`gau-bakery-ai` → **Disks** → **Add Disk**:
- Mount Path: `/app/data/chroma_db`
- Size: 1 GB

Không mua disk vẫn chạy được, chỉ cần reindex (bước 4) mỗi lần service restart.

---

## 4. Index knowledge base

Sau khi ai-service Live. Endpoint `/knowledge/index` có auth admin (HMAC) — gọi từ máy có `INTERNAL_API_SECRET`:

```bash
SECRET="<INTERNAL_API_SECRET>"
TS=$(date +%s)
SIG=$(python -c "import hmac,hashlib,sys; print(hmac.new(sys.argv[1].encode(), f'admin:{sys.argv[2]}'.encode(), hashlib.sha256).hexdigest())" "$SECRET" "$TS")
curl -X POST "https://gau-bakery-ai.onrender.com/knowledge/index?source=all" \
  -H "X-Admin-Bypass: ${TS}:${SIG}"
# {"status":"ok","indexed_count":<N>}
```

PowerShell:
```powershell
$secret = "<INTERNAL_API_SECRET>"
$ts = [int][double]::Parse((Get-Date -UFormat %s))
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$sig = ($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes("admin:$ts")) | ForEach-Object { $_.ToString("x2") }) -join ""
curl -X POST "https://gau-bakery-ai.onrender.com/knowledge/index?source=all" -H "X-Admin-Bypass: ${ts}:${sig}"
```

Rerun khi thêm sản phẩm mới, sửa `data/policies/*.md`, hoặc service restart (nếu không có disk).

---

## 5. Smoke test

```bash
# 1. Health
curl https://gau-bakery-ai.onrender.com/health
# {"status":"ok","engine":"multiagent"}

# 2. Chat qua PHP proxy (đi qua web → ai-service)
curl -X POST https://cake-i8l0.onrender.com/cakev0/api/chat/send.php \
  -H "Content-Type: application/json" \
  -d '{"message":"ship bao lâu","guest_token":"test-abc"}'
# {"session_id":...,"reply":{...},"handoff":false}
```

Browser: mở <https://cake-i8l0.onrender.com/cakev0/index.php> → nút 💬 góc phải dưới → gõ "menu bánh kem" → phải hiện product cards.

Admin inbox: đăng nhập admin → tab chat → thấy session + reply được.

---

## 6. Facebook Messenger (tùy chọn)

Render đã có HTTPS public, không cần ngrok. Full guide: `ai-service/docs/messenger-setup.md`.

1. Meta for Developers → Create App (Business) → Add Product Messenger.
2. Business Suite → tạo Page → generate Page Access Token → `FB_PAGE_TOKEN`.
3. App Settings → Basic → App Secret → `FB_APP_SECRET`.
4. Sinh verify token: `python -c "import secrets; print(secrets.token_hex(16))"` → `FB_VERIFY_TOKEN`.
5. Render `gau-bakery-ai` → Environment → set 3 var → Save.
6. Meta → Messenger → Webhooks → Add Callback URL:
   - URL: `https://gau-bakery-ai.onrender.com/channels/messenger/webhook`
   - Verify Token: `FB_VERIFY_TOKEN`
   - Fields: check **messages**.
7. Roles → Test Users → tạo tester, assign vào Page → nhắn thử.

---

## Troubleshooting

| Triệu chứng | Nguyên nhân | Fix |
|---|---|---|
| Web không hiện nút chat | Web vẫn deploy từ `main` | Bước 2 — deploy từ feat / merge |
| ai-service log `pymysql ... 2003/1045` | MySQL_SSL chưa bật hoặc pass sai | Set `MYSQL_SSL=true`, verify host/port/user Aiven |
| Widget "Không kết nối được" | `AI_SERVICE_URL` sai hoặc ai-service sleep | Verify URL, đợi cold start ~30s |
| `/chat/send` CORS blocked | `CORS_ORIGINS` ≠ origin web | Set = `https://cake-i8l0.onrender.com` |
| Order tạo lỗi `hmac mismatch` / `unauthorized` | `INTERNAL_API_SECRET` 2 service khác nhau | Đặt cùng value web + ai-service, restart cả 2 |
| `/knowledge/index` trả 403 | Header HMAC sai/hết hạn (>300s) | Sinh lại timestamp+sig ngay trước khi gọi |
| `indexed_count: 0` | Chưa có product trong DB | Verify banh_store data trên Aiven |
| Chroma mất data sau restart | Ephemeral disk | Add persistent disk (3.5) |
| Cold start 30s mỗi lần | Free tier sleep 15min idle | Upgrade Starter $7/mo hoặc cron ping /health |
