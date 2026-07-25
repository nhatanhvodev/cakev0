# Chuẩn bị Q&A Bảo vệ Khóa luận

> Câu hỏi dự kiến từ hội đồng + câu trả lời có dẫn chứng từ code/kiến trúc.
> Chia theo nhóm: Research Questions, Kiến trúc, Kỹ thuật, Đánh giá, Thực tiễn.

---

## A. Trả lời 5 Research Questions

### RQ1: Multi-agent có cải thiện độ chính xác so với RAG đơn giản không?

**Trả lời:** Có, nhờ 3 cơ chế:

1. **Router chuyên biệt** — Baseline dùng 1 prompt cho tất cả intent → dễ lẫn policy vào catalog answer. Multi-agent router phân luồng chính xác vào collection riêng (products, policies, faq).

2. **Per-collection retrieval** — Baseline search toàn bộ knowledge base. Multi-agent chỉ search collection phù hợp intent → ít noise, citation chính xác hơn.

3. **Retry mechanism** — Baseline không retry. Multi-agent rewrite query khi confidence < 0.5, tối đa 2 lần → tăng cơ hội trả lời đúng.

**Dẫn chứng code:**
- Router: `router.py:56-69` — keyword-first + LLM fallback
- Collection mapping: `retrieval.py:3-5` — intent → collection
- Retry: `graph.py:76-79` — `after_retrieval()` conditional edge

---

### RQ2: Multi-agent có tăng tỷ lệ câu trả lời grounded không?

**Trả lời:** Có. Retrieval agent trả JSON có `sources[]` trỏ về document ID cụ thể. Baseline trả text tự do, citation thường thiếu hoặc sai nguồn.

**Cơ chế:**
- Retrieval prompt yêu cầu `{"answer": "...", "sources": ["doc-id"]}` (`retrieval.py:7-8`)
- Citation được cross-reference với retrieved docs: `cits = [{"source": s, "excerpt": by_id[s].text[:120]} for s in parsed["sources"] if s in by_id]` (`retrieval.py:52`)
- Chỉ citation khớp với document thực tế mới được giữ lại

---

### RQ3: Handoff policy multi-agent có chính xác hơn không?

**Trả lời:** Có. Baseline dùng 1 signal duy nhất (confidence < 0.6). Multi-agent dùng 4 factors:

| Factor | Code | Mô tả |
|--------|------|--------|
| Intent-based | `handoff.py:8-9` | complaint/handoff_request → luôn handoff |
| Confidence | `handoff.py:10-11` | Dưới threshold → handoff |
| Keyword | `handoff.py:12-13` | "khiếu nại", "hoàn tiền gấp", "gặp quản lý" |
| Retry exhaustion | `handoff.py:14-15` | retry_count ≥ 2 → không retry thêm |

**Lợi ích:**
- Giảm false positive: câu đơn giản confidence thấp không bị handoff nếu không có keyword/intent match
- Giảm false negative: complaint có keyword nhưng confidence cao vẫn được handoff nhờ intent/keyword factor

---

### RQ4: Multi-agent có làm tăng độ trễ đáng kể không?

**Trả lời:** Tăng khoảng 1-1.5s, chấp nhận được.

| Bước | Baseline | Multi-agent |
|------|----------|-------------|
| Normalizer | ~10ms | ~10ms |
| Router | — | ~500ms (keyword) hoặc ~1.5s (LLM) |
| Retrieval | ~300ms | ~300ms |
| LLM Generate | ~1.5s | ~1.5s |
| **Tổng** | **~2s** | **~2.5-3.5s** |

**Tối ưu đã áp dụng:**
- Keyword-first router: 9 nhóm keyword, confidence ≥ 0.55 → skip LLM call (`router.py:56-58`). Tiết kiệm ~1s cho phần lớn queries.
- Chitchat path: 1 LLM call duy nhất (không retrieval)
- Telegram notify: async daemon thread, không block response (`notify.py`)

---

### RQ5: Tiền xử lý tiếng Việt có cải thiện chất lượng không?

**Trả lời:** Có, đặc biệt cho queries không dấu và teencode.

**Ví dụ:**
- "ko" → "không" (teencode dict: `normalizer.py:8-9`, `teencode.json`)
- "ship bao lau" → nhận diện intent `policy_shipping` nhờ keyword "ship"

**Ablation design:**
- `settings.enable_normalizer` flag (`graph.py:43-47`)
- `enable_normalizer=False` → skip normalize, pass raw query
- So sánh accuracy với/không normalizer trên cùng dataset

---

## B. Câu hỏi về Kiến trúc

### "Tại sao chọn LangGraph mà không dùng CrewAI hay AutoGen?"

LangGraph phù hợp nhất vì:

| Tiêu chí | LangGraph | CrewAI | AutoGen |
|----------|-----------|--------|---------|
| State management | StateGraph built-in | Không có | Multi-turn conversation |
| Conditional routing | `add_conditional_edges()` | Không linh hoạt | Nặng |
| Retry mechanism | Đồ thị có cycle (rewrite → retrieval) | Không hỗ trợ | Phức tạp |
| Lightweight | Ít dependency | Trung bình | Nhiều dependency |

Bài toán CSKH cần: routing có trạng thái, retry khi retrieval thất bại, conditional handoff. LangGraph match trực tiếp.

---

### "Tại sao dùng Gemini 2.0 Flash thay vì GPT-4?"

| Tiêu chí | Gemini 2.0 Flash | GPT-4o-mini |
|----------|-------------------|-------------|
| Chi phí | Free tier 1500 req/ngày | Trả phí |
| Tiếng Việt | Đủ tốt cho CSKH | Tốt hơn |
| Latency | ~1-1.5s | ~1-2s |
| Embedding | text-embedding-004 miễn phí | text-embedding-3-small trả phí |

Lý do: đề tài khóa luận, ngân sách hạn chế. Gemini free tier đủ cho development + thực nghiệm 150 mẫu. Kiến trúc LLM-agnostic — chỉ cần đổi `LLMClient` config để chuyển provider.

---

### "Tại sao ChromaDB mà không dùng Pinecone, Weaviate?"

- **Open-source, local:** không phụ thuộc cloud service, phù hợp ngân sách
- **Python-native:** tích hợp trực tiếp, không cần Docker cluster
- **Đủ cho scale:** ~50 products + 20 FAQ + 4 policies → ChromaDB thừa sức
- **Persistent:** `PersistentClient` lưu trên disk, restart không mất data

---

### "Giải thích cơ chế hybrid search (BM25 + dense)?"

**Phase 2 improvement:** kết hợp 2 loại search:

1. **Dense (ChromaDB):** Semantic similarity — hiểu nghĩa ("bánh cho bé" ≈ "cake for children")
2. **BM25 (keyword):** Exact term matching — bắt tên sản phẩm chính xác ("croissant", "tiramisu")

**Fusion:** Reciprocal Rank Fusion (RRF)
```
score(d) = Σ 1/(k + rank_i + 1),  k = 60
```

RRF ưu điểm: không cần normalize score giữa 2 hệ thống (BM25 score range khác cosine distance range). Chỉ dùng rank position.

**Dẫn chứng:** `vector_store.py:50-63` — `_rrf_fuse()`

---

## C. Câu hỏi Kỹ thuật Deep-dive

### "Keyword-first router có bỏ sót intent không?"

Không, vì keyword chỉ là fast path (confidence 0.55). Nếu không match keyword → fallback sang LLM router. Keyword coverage:

- 9 nhóm keyword, ~30 từ khóa tiếng Việt (`router.py:29-39`)
- Mỗi nhóm ưu tiên theo thứ tự (handoff_request trước complaint)
- Default: `faq` @ 0.4 nếu không match gì

Trade-off: tiết kiệm ~500-1500ms LLM call cho ~60-70% queries phổ biến.

---

### "State machine có deadlock hoặc infinite loop không?"

Không, nhờ 2 safeguards:

1. **Max retry:** `after_retrieval()` check `retry_count < 2` → tối đa 2 rewrite cycles (`graph.py:77`)
2. **All paths converge:** mọi agent node (retrieval, action, chitchat, handoff) đều edge đến `aggregate` → `END`
3. **Rewrite exhaustion:** nếu 2 retry fail → `should_handoff = True`, `needs_retry = False` → aggregate thay vì rewrite (`retrieval.py:71-74`)

---

### "Telegram notification có block response không?"

Không. `notify_handoff()` dùng `threading.Thread(daemon=True)` — fire-and-forget (`notify.py`). Response trả về client ngay, Telegram gửi async. Nếu Telegram API fail → silent skip, không ảnh hưởng UX.

---

### "DemoEngine fallback có phân biệt được intent không?"

Có, dùng `keyword_fallback()` từ router module — cùng logic keyword-first. 11 pre-scripted responses cover tất cả intents. Không dùng LLM (vì LLM đang lỗi), nhưng keyword đủ chính xác cho demo scenarios có script sẵn.

---

## D. Câu hỏi về Đánh giá

### "Dataset 150 mẫu có đủ tin cậy thống kê không?"

- 150 mẫu đủ cho paired comparison (Wilcoxon signed-rank test, không yêu cầu normality)
- Cohen's Kappa > 0.8 cho inter-annotator agreement → đảm bảo label quality
- Hạn chế thừa nhận: kết quả có thể không generalize sang domain khác

---

### "Tại sao dùng Wilcoxon test mà không dùng t-test?"

- Wilcoxon: non-parametric, không yêu cầu dữ liệu phân phối chuẩn
- Phù hợp cho accuracy scores (0/1 binary) và confidence scores (bounded 0-1)
- Paired test: so sánh cùng query trên 2 hệ thống → paired design

---

### "Ablation study thiết kế thế nào?"

So sánh 4 configurations trên cùng dataset:

| Config | Normalizer | Engine | Hybrid Search |
|--------|-----------|--------|---------------|
| A (Baseline) | On | BaselineEngine | Dense only |
| B (Multi-agent) | On | MultiAgentEngine | Hybrid (dense + BM25) |
| B' (No normalizer) | **Off** | MultiAgentEngine | Hybrid |
| B'' (Dense only) | On | MultiAgentEngine | **Dense only** |

Cho phép isolate đóng góp của: (a) multi-agent routing, (b) Vietnamese normalizer, (c) hybrid search.

**Implementation:**
- Normalizer toggle: `settings.enable_normalizer` (`graph.py:43-47`)
- Engine toggle: `settings.engine` = baseline | multiagent (`deps.py`)
- Hybrid toggle: BM25 index chỉ build khi `add()` được gọi; không build = dense only (`vector_store.py:88-91`)

---

## E. Câu hỏi Thực tiễn

### "Hệ thống có thể deploy production không?"

Đã deploy: https://cake-i8l0.onrender.com/cakev0/

| Component | Production-ready? | Cần cải thiện |
|-----------|-------------------|---------------|
| Chat widget | Có | WebSocket thay polling |
| Multiagent engine | Có | Streaming responses |
| Vector store | Có (persistent) | Auto-reindex on data change |
| Handoff + ticket | Có | Admin dashboard UI |
| Telegram notify | Có | Email + SMS channels |

---

### "Chi phí vận hành thực tế?"

| Resource | Chi phí | Giới hạn |
|----------|---------|----------|
| Gemini API | Miễn phí | 1500 req/ngày |
| Render (FastAPI) | Miễn phí | Cold start 30s |
| Aiven MySQL | Miễn phí | 1GB storage |
| ChromaDB | Miễn phí (local) | Disk space |
| Telegram Bot | Miễn phí | Unlimited |
| **Tổng** | **$0/tháng** | Đủ cho SME nhỏ |

Scale lên: Render paid ($7/tháng no cold start), Gemini paid ($0.15/1M tokens) → ~$10/tháng cho 1000 conversations/ngày.

---

### "SME khác có thể tái sử dụng không?"

Có, kiến trúc domain-agnostic:

1. Thay knowledge base: index products/policies/FAQ của domain mới vào ChromaDB
2. Thay teencode dict: cập nhật `teencode.json` cho domain
3. Giữ nguyên: router, retrieval, handoff, action agents
4. Config: đổi system prompts trong `ROUTER_SYSTEM`, `RETRIEVAL_SYSTEM`

Cần customize: order schema (MySQL tables), PHP API endpoints.

---

### "Hạn chế chính của đề tài?"

| Hạn chế | Mức độ | Giải pháp tương lai |
|---------|--------|---------------------|
| Dataset synthetic (không có user thật) | Cao | User study sau deploy |
| 1 case study (bánh online) | Trung bình | Test trên domain khác |
| Không fine-tune model | Thấp | Fine-tune SLM (Qwen/Llama) |
| Polling thay WebSocket | Thấp | Upgrade client |
| Free tier LLM | Trung bình | Paid tier cho production |
