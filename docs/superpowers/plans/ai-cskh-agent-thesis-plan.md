# Đề tài khóa luận tốt nghiệp: Trợ lý CSKH & Chốt đơn Đa kênh cho SME TMĐT dựa trên kiến trúc Multi-Agent RAG

> **Loại tài liệu:** Kế hoạch nghiên cứu khóa luận tốt nghiệp | **Ngành:** CNTT/KHMT
> **Thời gian dự kiến:** 3-4 tháng | **Case study:** Gấu Bakery
> **Website:** https://cake-i8l0.onrender.com/cakev0/

---

## Mục lục

1. [Tóm tắt đề tài](#1-tóm-tắt-đề-tài)
2. [Mục tiêu nghiên cứu](#2-mục-tiêu-nghiên-cứu)
3. [Câu hỏi nghiên cứu](#3-câu-hỏi-nghiên-cứu)
4. [Tổng quan nghiên cứu (Literature Review)](#4-tổng-quan-nghiên-cứu)
5. [Cơ sở lý thuyết & Công nghệ](#5-cơ-sở-lý-thuyết--công-nghệ)
6. [Phân tích bài toán & Dữ liệu](#6-phân-tích-bài-toán--dữ-liệu)
7. [Thiết kế hệ thống - Hai kiến trúc đối sánh](#7-thiết-kế-hệ-thống---hai-kiến-trúc-đối-sánh)
8. [Phương pháp thực nghiệm & Đánh giá](#8-phương-pháp-thực-nghiệm--đánh-giá)
9. [Kế hoạch triển khai](#9-kế-hoạch-triển-khai)
10. [Dự kiến kết quả & Đóng góp](#10-dự-kiến-kết-quả--đóng-góp)
11. [Hạn chế & Hướng phát triển](#11-hạn-chế--hướng-phát-triển)
12. [Tài liệu tham khảo](#12-tài-liệu-tham-khảo)

---

## 1. Tóm tắt đề tài

### 1.1. Bối cảnh

TMĐT Việt Nam tăng trưởng 25%+/năm. Các SME phải xử lý hàng trăm tin nhắn/ngày từ website chat, Facebook, Zalo. AI Agent CSKH là giải pháp khả thi về chi phí cho phân khúc này. Tuy nhiên, phần lớn chatbot dừng ở mức FAQ đơn giản. Thực tế đòi hỏi agent phân luồng chính xác giữa hỏi đáp, gợi ý sản phẩm, truy vấn đơn hàng và khiếu nại - bằng tiếng Việt đời thường.

### 1.2. Mục tiêu

So sánh hiệu quả pipeline RAG đơn giản với kiến trúc multi-agent (LangGraph) trong CSKH TMĐT tiếng Việt, đo lường qua 5 metrics.

### 1.3. Đối tượng & Phạm vi

- Case study: Gấu Bakery (~50 SP, 4 chính sách, PHP/MySQL)
- Người dùng: Khách hàng tiếng Việt, ngôn ngữ đời thường
- Kênh: Website chat widget
- Phạm vi kỹ thuật: Không train model; dùng LLM API + RAG + multi-agent

### 1.4. Đóng góp dự kiến

1. Bộ tiêu chí đánh giá định lượng cho AI agent CSKH tiếng Việt
2. So sánh thực nghiệm 2 kiến trúc agent
3. Dataset hội thoại CSKH bánh online tiếng Việt (100-200 mẫu)
4. Hệ thống demo triển khai thực tế trên Gấu Bakery

## 2. Mục tiêu nghiên cứu

### 2.1. Mục tiêu tổng quát

Xây dựng và so sánh hai kiến trúc AI Agent CSKH cho SME TMĐT: (A) pipeline RAG đơn giản và (B) kiến trúc multi-agent với LangGraph, nhằm xác định kiến trúc tối ưu cho CSKH tiếng Việt trong môi trường thực tế.

### 2.2. Mục tiêu cụ thể

| # | Mục tiêu | Đầu ra |
|---|----------|--------|
| M1 | Thiết kế kiến trúc Baseline: RAG đơn giản (single-pipeline) | Hệ thống A |
| M2 | Thiết kế kiến trúc Proposed: Multi-agent LangGraph (Router + Retrieval + Action + Handoff) | Hệ thống B |
| M3 | Xây dựng dataset hội thoại CSKH tiếng Việt cho case study | 100-200 hội thoại |
| M4 | Định nghĩa bộ 5 metrics đánh giá | Framework đo lường |
| M5 | Chạy thực nghiệm so sánh A/B trên cùng dataset | Kết quả định lượng |
| M6 | Phân tích kết quả, rút ra kết luận | Báo cáo + demo |

---

## 3. Câu hỏi nghiên cứu (Research Questions)

| # | Câu hỏi | Giả thuyết |
|---|---------|------------|
| RQ1 | Kiến trúc multi-agent có cải thiện độ chính xác so với RAG đơn giản không? | Cải thiện 15-25% nhờ routing đúng tool |
| RQ2 | Multi-agent có tăng tỷ lệ câu trả lời grounded (có trích nguồn) không? | Tăng grounded rate nhờ retrieval agent chuyên biệt |
| RQ3 | Handoff policy trong multi-agent có chính xác hơn confidence threshold đơn giản không? | Giảm false positive handoff |
| RQ4 | Multi-agent có làm tăng độ trễ phản hồi đáng kể không? | Tăng <1s, chấp nhận được |
| RQ5 | Tiền xử lý tiếng Việt có cải thiện chất lượng không? | Cải thiện 10-15% accuracy |

---

## 4. Tổng quan nghiên cứu (Literature Review)

### 4.1. Retrieval-Augmented Generation (RAG)

RAG (Lewis et al., 2020) kết hợp retrieval từ knowledge base với text generation của LLM. Ưu điểm: tri thức luôn cập nhật, giảm hallucination, có citation. Các biến thể: Naive RAG (retrieve → generate), Advanced RAG (query rewriting, reranking), Modular RAG (tách module, kết hợp agent).

### 4.2. Multi-Agent Architectures

Multi-agent (Wu et al., 2023; Hong et al., 2024) phân chia task phức tạp thành sub-task do agent chuyên biệt xử lý:

| Agent | Vai trò |
|-------|---------|
| Router Agent | Phân luồng yêu cầu đến agent phù hợp |
| Retrieval Agent | Truy xuất thông tin từ knowledge base |
| Action Agent | Thực thi hành động (tạo đơn, cập nhật trạng thái) |
| Handoff Policy | Quyết định chuyển tiếp người thật |

So sánh framework:

| Framework | Điểm mạnh | Điểm yếu | Phù hợp khi |
|-----------|-----------|----------|-------------|
| **LangGraph** | Stateful graph, retry, conditional routing | Learning curve cao | Workflow phức tạp, cần state management |
| **CrewAI** | Role-based, dễ setup, mô hình nhóm | Ít linh hoạt cho flow phức tạp | Mô hình sales-support-reviewer |
| **AutoGen** | Multi-turn conversation giữa agents | Nặng, nhiều dependency | Research, prototyping |
| **LlamaIndex** | Indexing mạnh (PDF, HTML, SQL) | Không có state machine | Catalog nhiều định dạng tài liệu |

**Lựa chọn cho đề tài:** LangGraph (cho Proposed system) + LangChain (cho Baseline). LangGraph phù hợp nhất vì bài toán cần routing có trạng thái, retry khi retrieval thất bại, và conditional handoff.

### 4.3. AI Agent trong CSKH TMĐT

- E-commerce chatbots: chủ yếu FAQ + product recommendation (Cui et al., 2023)
- Task-oriented dialogue: tích hợp API call để thực thi (order lookup, booking)
- ShoppingGPT (2024): open-source multi-agent e-commerce
- Vietnamese chatbots: hạn chế, chủ yếu intent classification + retrieval

### 4.4. Xử lý tiếng Việt trong NLP

Thách thức chính:

| Vấn đề | Ví dụ | Giải pháp |
|--------|-------|-----------|
| Thiếu dấu | "com ga" → "cơm gà" | Diacritics restoration (BARTpho) |
| Teencode | "ko", "dc", "j", "r" | Dictionary normalization |
| Viết tắt | "sp", "đt", "ship" | Domain-specific dictionary |
| Từ mượn TMĐT | COD, freeship, bill đỏ | Glossary mapping |

### 4.5. Khoảng trống nghiên cứu (Research Gap)

| Khoảng trống | Đề tài này giải quyết |
|--------------|----------------------|
| Thiếu so sánh định lượng RAG vs multi-agent cho CSKH tiếng Việt | Thực nghiệm A/B |
| Chatbot tiếng Việt chủ yếu FAQ đơn giản | Multi-agent với action agent |
| Chưa có dataset hội thoại CSKH bánh online tiếng Việt | Tạo dataset mới |
| Thiếu framework đánh giá toàn diện agent CSKH | Bộ 5 metrics |


## 5. Cơ sở lý thuyết & Công nghệ

### 5.1. Large Language Models (LLMs)

Dùng API model có sẵn, không fine-tune:

| Model | Provider | Điểm mạnh | Hạn chế |
|-------|----------|-----------|---------|
| GPT-4o-mini | OpenAI | Nhanh, rẻ, tiếng Việt tốt | Cần API key trả phí |
| Gemini 1.5 Flash | Google | Free tier 1500 req/ngày | Tiếng Việt kém hơn GPT |
| Claude 3 Haiku | Anthropic | An toàn, chính xác | Chậm hơn, đắt hơn |

### 5.2. Embedding Models

| Model | Dimensions | Ưu điểm |
|-------|-----------|---------|
| text-embedding-3-small (OpenAI) | 1536 | Rẻ, đa ngôn ngữ tốt |
| text-embedding-004 (Google) | 768 | Free tier generous |
| BGE-M3 (BAAI) | 1024 | Open-source, multiligual |

### 5.3. Vector Database

**ChromaDB** - Lựa chọn cho đề tài vì: open-source, local, nhẹ, Python-native, tích hợp LangChain.

### 5.4. LangChain & LangGraph

- **LangChain:** Framework orchestration cho LLM app, cung cấp RAG chain, prompt template, memory
- **LangGraph:** Extension của LangChain cho stateful multi-agent workflow, hỗ trợ conditional routing, retry, human-in-the-loop

### 5.5. Công nghệ triển khai

| Layer | Technology |
|-------|-----------|
| Backend API | FastAPI (Python 3.12) |
| Web Server | Uvicorn + Gunicorn |
| Chat Widget | HTML/CSS/JS (Vanilla) |
| Existing System | PHP 8.2 + MySQL 8.0 |
| Deployment | Docker + Render |

---

## 6. Phân tích bài toán & Dữ liệu

### 6.1. Đặc thù bài toán CSKH bánh online

1. **Ngôn ngữ:** Tiếng Việt đời thường, thiếu dấu, teencode, từ mượn TMĐT
2. **Intent đa dạng:** Hỏi giá, hỏi size, đặt bánh gấp, đổi địa chỉ, hủy đơn, khiếu nại
3. **Knowledge source rời rạc:** Catalog SP trong MySQL, chính sách trong PHP pages
4. **Yêu cầu thời gian thực:** Tra cứu đơn cần gọi API đến DB

### 6.2. Phân tích Intent

| Intent | Tỷ lệ ước tính | Ví dụ | Agent xử lý |
|--------|---------------|-------|-------------|
| catalog_search | 30% | "có bánh sinh nhật không?" | Retrieval Agent |
| faq | 20% | "giao hàng bao lâu?" | Retrieval Agent |
| order_status | 15% | "đơn 123 đến đâu rồi?" | Action Agent |
| product_recommend | 15% | "sinh nhật bé trai nên mua bánh gì?" | Retrieval Agent |
| policy | 10% | "đổi bánh được không?" | Retrieval Agent |
| complaint | 5% | "bánh bị hỏng khi nhận" | Handoff Policy |
| chitchat | 3% | "cảm ơn", "chào" | Router → generate |
| handoff_request | 2% | "cho gặp người thật" | Handoff Policy |

### 6.3. Yêu cầu xử lý tiếng Việt

Module tiền xử lý (Vietnamese Text Normalizer):

```
Input: "shop oi, cho e hoi banh sn co ko a, ship bao lau vay"

Step 1: Diacritics restoration (BARTpho / rule-based)
       "shop ơi, cho e hỏi bánh sn có ko ạ, ship bao lâu vậy"

Step 2: Teencode normalization
       "shop ơi, cho em hỏi bánh sinh nhật có không ạ, giao bao lâu vậy"

Step 3: Standardize → Intent classification
```

### 6.4. Dataset thiết kế

| Thành phần | Mô tả | Số lượng |
|------------|-------|----------|
| Hội thoại mẫu | Hội thoại thực tế + synthetic | 100-200 |
| Label mỗi hội thoại | Intent, expected response, handoff (yes/no) | 100% |
| Ground truth | Câu trả lời chuẩn cho mỗi intent | Manual annotation |
| Split | 70% test evaluation, 30% development | |

Nguồn dữ liệu:
- Mô phỏng từ kinh nghiệm bán hàng thực tế
- Synthetic generation từ LLM (prompt: mô phỏng khách hàng bánh online)
- Manual review để đảm bảo tính thực tế


## 7. Thiết kế hệ thống - Hai kiến trúc đối sánh

### 7.1. Kiến trúc A: Baseline (RAG đơn giản - Single Pipeline)

```
┌──────────┐    ┌─────────────────┐    ┌──────────────┐    ┌──────────┐
│  Input   │───▶│  Vietnamese     │───▶│  Embedding    │───▶│ ChromaDB │
│ (user)   │    │  Normalizer     │    │  (query)      │    │ (search) │
└──────────┘    └─────────────────┘    └──────┬───────┘    └────┬─────┘
                                              │                  │
                                              ▼                  ▼
┌──────────┐    ┌─────────────────┐    ┌──────────────────────────┐
│  Output  │◀───│  LLM Generate   │◀───│  Prompt: system + docs   │
│ (answer) │    │  (single call)  │    │  + query + chat history  │
└──────────┘    └─────────────────┘    └──────────────────────────┘
```

**Đặc điểm:**
- 1 LLM call duy nhất cho mọi intent
- Prompt dài chứa tất cả instruction (FAQ, catalog, policy)
- Confidence = score từ LLM output parsing
- Handoff: if confidence < 0.6 then escalate
- Không phân biệt intent → không routing → dễ hallucinate sai lĩnh vực

**System Prompt (Baseline):**
```
Bạn là trợ lý CSKH của Gấu Bakery. Dựa vào tài liệu được cung cấp,
trả lời câu hỏi của khách. Nếu không chắc chắn, đề nghị chuyển người thật.

CÁC NHIỆM VỤ:
- Trả lời câu hỏi về sản phẩm, giá cả
- Giải thích chính sách giao hàng, đổi trả, thanh toán
- Gợi ý sản phẩm phù hợp
- Kiểm tra trạng thái đơn hàng
- Chuyển tiếp khiếu nại cho nhân viên

TÀI LIỆU THAM KHẢO:
{documents}
```

### 7.2. Kiến trúc B: Proposed (Multi-Agent với LangGraph)

```
                            ┌──────────────────────┐
                            │   Vietnamese         │
                            │   Normalizer         │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │   Router Agent       │
                            │   (Intent Classify)  │
                            └──────────┬───────────┘
                                       │
              ┌────────────────┬───────┼───────┬────────────────┐
              │                │       │       │                │
              ▼                ▼       │       ▼                ▼
    ┌─────────────┐  ┌─────────────┐   │  ┌──────────┐  ┌─────────────┐
    │ Retrieval   │  │ Action      │   │  │ Chitchat │  │ Handoff     │
    │ Agent       │  │ Agent       │   │  │ Agent    │  │ Policy      │
    │ (FAQ+Cat)   │  │ (Order+CUD) │   │  │          │  │ Agent       │
    └──────┬──────┘  └──────┬──────┘   │  └────┬─────┘  └──────┬──────┘
           │                │          │       │               │
           ▼                ▼          │       ▼               ▼
    ┌─────────────┐  ┌─────────────┐   │  ┌──────────┐  ┌─────────────┐
    │ RAG Engine  │  │ MySQL API   │   │  │ Simple   │  │ Ticket      │
    │ ChromaDB    │  │ (orders)    │   │  │ Generate │  │ + Draft     │
    └──────┬──────┘  └──────┬──────┘   │  └────┬─────┘  └──────┬──────┘
           │                │          │       │               │
           └────────────────┴──────────┴───────┴───────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │   Response           │
                            │   Aggregator         │
                            │   (format + citation)│
                            └──────────────────────┘
```

**LangGraph State Machine:**

```python
from langgraph.graph import StateGraph, END

class AgentState(TypedDict):
    query: str
    normalized_query: str
    intent: str
    confidence: float
    retrieved_docs: list
    action_result: dict
    response: str
    should_handoff: bool
    retry_count: int

workflow = StateGraph(AgentState)

# Nodes
workflow.add_node("normalize", normalize_vietnamese)
workflow.add_node("router", router_agent)
workflow.add_node("retrieval", retrieval_agent)
workflow.add_node("action", action_agent)
workflow.add_node("chitchat", chitchat_agent)
workflow.add_node("handoff", handoff_policy)
workflow.add_node("aggregate", response_aggregator)

# Edges với conditional routing
workflow.set_entry_point("normalize")
workflow.add_edge("normalize", "router")

workflow.add_conditional_edges(
    "router",
    route_by_intent,
    {
        "faq": "retrieval",
        "catalog_search": "retrieval",
        "product_recommend": "retrieval",
        "policy_shipping": "retrieval",
        "policy_payment": "retrieval",
        "policy_return": "retrieval",
        "order_status": "action",
        "order_create": "action",
        "complaint": "handoff",
        "handoff_request": "handoff",
        "chitchat": "chitchat"
    }
)

workflow.add_edge("retrieval", "aggregate")
workflow.add_edge("action", "aggregate")
workflow.add_edge("chitchat", "aggregate")

# Handoff có thể retry hoặc kết thúc
workflow.add_conditional_edges(
    "handoff",
    decide_after_handoff,
    {"retry": "retrieval", "end": END}
)

workflow.add_edge("aggregate", END)

app = workflow.compile()
```

### 7.3. So sánh hai kiến trúc

| Tiêu chí | Baseline (RAG đơn giản) | Proposed (Multi-Agent) |
|----------|------------------------|------------------------|
| **Số LLM call** | 1 call/turn | 2-3 calls/turn (router + agent + aggregate) |
| **Routing** | Không có (1 prompt cho tất cả) | Router agent phân luồng |
| **Retrieval** | Single-stage, tất cả nguồn gộp | Per-agent retrieval (SP riêng, policy riêng) |
| **Action** | Không (chỉ generate text) | Action agent gọi API tra cứu đơn |
| **Handoff** | Rule-based (confidence < 0.6) | Handoff Policy Agent (multi-factor) |
| **Retry** | Không | Có (retry retrieval với query rewrite) |
| **Citation** | Có nhưng dễ lẫn nguồn | Citation rõ ràng theo agent |
| **Latency** | ~2-3s | ~3-5s (nhiều call hơn) |
| **Cost** | Thấp (1 call) | Cao hơn (2-3 calls) |
| **Debug** | Khó (1 prompt dài) | Dễ (log từng agent) |

### 7.4. Chi tiết từng Agent trong Proposed

#### Router Agent

```python
class RouterAgent:
    """Phân loại intent và chọn agent phù hợp"""

    SYSTEM_PROMPT = """
    Bạn là router trong hệ thống CSKH Gấu Bakery.
    Phân loại câu hỏi vào 1 trong các intent sau:
    - faq: câu hỏi chung về cửa hàng, cách đặt bánh
    - catalog_search: tìm sản phẩm cụ thể theo tên, loại
    - product_recommend: nhờ gợi ý sản phẩm cho dịp
    - order_status: kiểm tra trạng thái đơn hàng
    - order_create: muốn đặt bánh mới
    - policy_shipping: hỏi về vận chuyển, phí ship
    - policy_payment: hỏi về thanh toán, COD, chuyển khoản
    - policy_return: hỏi về đổi trả, hoàn tiền
    - complaint: phàn nàn, khiếu nại
    - handoff_request: yêu cầu gặp người thật
    - chitchat: chào hỏi, cảm ơn

    OUTPUT FORMAT: JSON {"intent": "...", "confidence": 0.0-1.0}
    """
```

#### Retrieval Agent

```python
class RetrievalAgent:
    """Truy xuất và sinh câu trả lời từ knowledge base"""

    def process(self, query, intent):
        # Step 1: Chọn collection dựa trên intent
        if intent in ["catalog_search", "product_recommend"]:
            collection = "products"
        elif intent.startswith("policy_"):
            collection = "policies"
        else:
            collection = "faq"

        # Step 2: Retrieval với filter
        docs = chroma.search(query, collection=collection, top_k=5)

        # Step 3: Rerank nếu cần
        if intent == "product_recommend":
            docs = self.rerank_by_promotion(docs)

        # Step 4: Generate answer với citation
        answer = llm.generate(
            system_prompt=RETRIEVAL_PROMPT,
            documents=docs,
            query=query
        )
        return answer, docs
```

#### Action Agent

```python
class ActionAgent:
    """Thực thi hành động: tra cứu đơn, tạo đơn"""

    def process(self, query, intent, user_context):
        if intent == "order_status":
            # Extract phone number từ query
            phone = extract_phone(query)
            # Gọi MySQL API
            orders = db.query(
                "SELECT * FROM orders WHERE phone = %s ORDER BY created_at DESC LIMIT 5",
                [phone]
            )
            # Format kết quả
            return format_order_cards(orders)

        elif intent == "order_create":
            # Tạo draft đơn hàng
            return {
                "action": "create_order_draft",
                "redirect": "/cakev0/pages/cart.php",
                "message": "Mời bạn vào giỏ hàng để hoàn tất đặt bánh"
            }
```

#### Handoff Policy Agent

```python
class HandoffPolicyAgent:
    """Quyết định có chuyển tiếp người thật không"""

    def decide(self, state: AgentState) -> bool:
        # Multi-factor decision
        reasons = []

        # Factor 1: Intent-based (luôn handoff)
        if state["intent"] in ["complaint", "handoff_request"]:
            reasons.append("intent_triggers_handoff")

        # Factor 2: Confidence-based
        if state["confidence"] < 0.6:
            reasons.append(f"low_confidence_{state['confidence']}")

        # Factor 3: Keyword-based
        HANDOFF_KEYWORDS = ["khiếu nại", "hoàn tiền gấp", "gặp quản lý"]
        if any(kw in state["query"].lower() for kw in HANDOFF_KEYWORDS):
            reasons.append("keyword_match")

        # Factor 4: Retry count
        if state["retry_count"] >= 2:
            reasons.append(f"max_retries_{state['retry_count']}")

        should_handoff = len(reasons) > 0

        if should_handoff:
            # Tạo ticket với draft response
            self.create_ticket(state, reasons)

        return should_handoff
```


## 8. Phương pháp thực nghiệm & Đánh giá

### 8.1. Thiết kế thực nghiệm

```
┌─────────────────────────────────────────────────────┐
│                 Dataset (150 hội thoại)              │
│  70% đa dạng intent | 30% edge cases + noise        │
└──────────────────────┬──────────────────────────────┘
                       │
          ┌────────────┴────────────┐
          │                         │
          ▼                         ▼
┌─────────────────┐       ┌─────────────────┐
│  System A       │       │  System B       │
│  (Baseline RAG) │       │  (Multi-Agent)  │
└────────┬────────┘       └────────┬────────┘
         │                         │
         └──────────┬──────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────┐
│            Đo lường 5 Metrics                       │
│  Accuracy | Grounded Rate | Handoff Accuracy        │
│  First Response Time | Retention Rate               │
└─────────────────────────────────────────────────────┘
```

### 8.2. Định nghĩa 5 Metrics

#### M1: Answer Accuracy (Độ chính xác câu trả lời)

```
Metric: Tỷ lệ câu trả lời đúng trên tổng số câu hỏi
Đo bằng: Manual evaluation bởi 2 annotator (Cohen''s Kappa > 0.8)
Thang đo: 0-1 (Correct / Incorrect)
Tiêu chí đúng:
  - Trả lời đúng ý khách hỏi
  - Thông tin chính xác (giá đúng, policy đúng, trạng thái đơn đúng)
  - Không bịa đặt thông tin không có trong knowledge base
```

#### M2: Grounded Response Rate (Tỷ lệ câu trả lời có căn cứ)

```
Metric: Tỷ lệ câu trả lời có trích dẫn nguồn chính xác
Đo bằng: Tự động kiểm tra citation có khớp với retrieved documents
Công thức: GRR = (số câu có citation đúng) / (tổng số câu trả lời)
Citation đúng khi:
  - Có ít nhất 1 citation
  - Nội dung citation khớp với claim trong câu trả lời
  - Citation đến từ đúng knowledge source
```

#### M3: Handoff Accuracy (Tỷ lệ handoff đúng)

```
Metric: Độ chính xác của quyết định handoff
Đo bằng: So sánh handoff decision với ground truth label

Confusion Matrix:
                     Ground Truth
                   Should    Should NOT
                   Handoff   Handoff
Predicted Handoff    TP        FP
Predicted NOT        FN        TN

Metrics phụ:
  - Precision = TP / (TP + FP)   [Khi agent nói cần handoff, bao nhiêu lần đúng?]
  - Recall    = TP / (TP + FN)   [Trong các case cần handoff, agent bắt được bao nhiêu?]
  - F1 Score
```

#### M4: First Response Time (Thời gian phản hồi đầu tiên)

```
Metric: Thời gian từ lúc user gửi message đến khi nhận được token đầu tiên
Đo bằng: Server-side timestamp (ms)
Đơn vị: milliseconds
Bao gồm: Normalization + Intent Classification + Retrieval + Generation

So sánh: Baseline (1 LLM call) vs Proposed (2-3 LLM calls + routing overhead)
```

#### M5: Conversation Retention Rate (Tỷ lệ giữ khách trong hội thoại)

```
Metric: Tỷ lệ người dùng tiếp tục hội thoại sau câu trả lời đầu tiên
Đo bằng: (số session có >= 3 messages từ user) / (tổng số session)
Ý nghĩa: Đo lường mức độ hài lòng và engagement
Giả thuyết: Hệ thống trả lời tốt → user tiếp tục hỏi → retention cao
```

### 8.3. Quy trình đánh giá

```
Bước 1: Chuẩn bị dataset (150 hội thoại)
  - 100 hội thoại phổ biến (70% training/dev)
  - 50 hội thoại edge cases (30% test)
  - Mỗi hội thoại: [messages] + [expected_intent] + [expected_handoff] + [ground_truth_answer]

Bước 2: Chạy cả 2 hệ thống trên dataset
  - Ghi log: intent, confidence, retrieved_docs, response, latency
  - Lưu kết quả vào kết quả CSV

Bước 3: Tính toán metrics tự động
  - Grounded rate: script kiểm tra citation
  - Handoff accuracy: compare với ground truth
  - First response time: từ log latency
  - Retention rate: đếm session length

Bước 4: Manual evaluation (Accuracy)
  - 2 annotator đánh giá độc lập
  - Tính Cohen''s Kappa cho inter-annotator agreement
  - Yêu cầu Kappa > 0.8 mới chấp nhận kết quả

Bước 5: Phân tích thống kê
  - Paired t-test hoặc Wilcoxon signed-rank test
  - So sánh mean của từng metric giữa 2 hệ thống
  - p-value < 0.05 → khác biệt có ý nghĩa thống kê
```

### 8.4. Baseline để so sánh

Ngoài 2 kiến trúc chính, có thể thêm:

| System | Mô tả | Dùng để |
|--------|-------|---------|
| **System 0** | LLM không RAG (chỉ system prompt) | Lower bound |
| **System A** | RAG đơn giản | Chính - Baseline |
| **System B** | Multi-Agent LangGraph | Chính - Proposed |
| **System B''** | Multi-Agent + Vietnamese Normalizer | Ablation study |

---


## 9. Kế hoạch triển khai

### 9.1. Timeline tổng thể (14 tuần)

```
Tuần 1-2   | Literature Review + System Design
Tuần 3-4   | Xây dựng Baseline System (RAG đơn giản)
Tuần 5-7   | Xây dựng Proposed System (Multi-Agent LangGraph)
Tuần 8-9   | Xây dựng Dataset + Vietnamese Normalizer
Tuần 10-11 | Thực nghiệm + Đo lường 5 metrics
Tuần 12    | Phân tích kết quả + Viết báo cáo
Tuần 13    | Demo + Slide bảo vệ
Tuần 14    | Chỉnh sửa + Nộp
```

### 9.2. Chi tiết từng giai đoạn

#### Giai đoạn 1: Nền tảng (Tuần 1-2)

- [ ] Tổng hợp literature review (RAG, multi-agent, Vietnamese NLP)
- [ ] Thiết lập môi trường: Python/FastAPI, ChromaDB, MySQL
- [ ] Tích hợp multi-LLM provider (OpenAI, Gemini, Claude)
- [ ] Viết migration SQL (chat_sessions, chat_messages, faq_entries, support_tickets)
- [ ] Xây dựng knowledge base indexing pipeline
- [ ] Index catalog (~50 sản phẩm) + 4 chính sách vào ChromaDB
- [ ] Viết 15-20 FAQ mẫu

**Deliverable:** Môi trường hoạt động + Knowledge base đã index

#### Giai đoạn 2: Baseline System (Tuần 3-4)

- [ ] Thiết kế system prompt cho single-pipeline RAG
- [ ] Implement POST /api/chat/send (baseline version)
- [ ] Implement confidence scoring từ LLM output
- [ ] Implement rule-based handoff (confidence < 0.6)
- [ ] Xây dựng chat widget frontend (polling-based)
- [ ] Tạo PHP API proxy endpoints
- [ ] Integration test với Gấu Bakery website

**Deliverable:** System A hoạt động end-to-end

#### Giai đoạn 3: Proposed System (Tuần 5-7)

- [ ] Thiết kế LangGraph state machine
- [ ] Implement Router Agent (intent classifier chuyên biệt)
- [ ] Implement Retrieval Agent (per-collection retrieval + rerank)
- [ ] Implement Action Agent (order lookup API)
- [ ] Implement Handoff Policy Agent (multi-factor decision)
- [ ] Implement Response Aggregator (format + citation)
- [ ] Implement retry mechanism (query rewriting khi retrieval thất bại)
- [ ] Integration test với cùng frontend

**Deliverable:** System B hoạt động end-to-end

#### Giai đoạn 4: Dataset & Vietnamese Normalizer (Tuần 8-9)

- [ ] Viết Vietnamese Text Normalizer:
  - Diacritics restoration (dùng BARTpho hoặc rule-based)
  - Teencode dictionary (50+ mappings)
  - TMĐT glossary (COD, freeship, bill đỏ...)
- [ ] Xây dựng dataset 150 hội thoại:
  - 100 mẫu từ mô phỏng + synthetic
  - 50 edge cases (thiếu dấu, nhiễu, teencode)
  - Manual annotation (intent, handoff, ground truth answer)
- [ ] Validate dataset chất lượng

**Deliverable:** Dataset hoàn chỉnh + Normalizer module

#### Giai đoạn 5: Thực nghiệm (Tuần 10-11)

- [ ] Chạy System A trên toàn bộ dataset, ghi log
- [ ] Chạy System B trên toàn bộ dataset, ghi log
- [ ] (Optional) Chạy ablation: System B không có Normalizer
- [ ] Tính toán 5 metrics cho từng system
- [ ] Manual evaluation (2 annotator, Cohen''s Kappa)
- [ ] Phân tích thống kê (t-test, effect size)
- [ ] Ghi nhận qualitative observations

**Deliverable:** Bảng kết quả thực nghiệm

#### Giai đoạn 6: Báo cáo & Demo (Tuần 12-14)

- [ ] Viết báo cáo khóa luận (theo template trường)
- [ ] Vẽ biểu đồ so sánh metrics
- [ ] Phân tích kết quả, trả lời 5 research questions
- [ ] Chuẩn bị slide bảo vệ
- [ ] Demo system online (Render deploy)
- [ ] Chỉnh sửa theo feedback giảng viên

**Deliverable:** Báo cáo + Slide + Demo

---

## 10. Dự kiến kết quả & Đóng góp

### 10.1. Kết quả định lượng dự kiến

| Metric | System A (Baseline) | System B (Multi-Agent) | Delta |
|--------|--------------------|------------------------|-------|
| Answer Accuracy | ~70-75% | ~85-90% | +15-20% |
| Grounded Rate | ~60-65% | ~85-90% | +20-25% |
| Handoff Precision | ~70% | ~88% | +18% |
| Handoff Recall | ~80% | ~92% | +12% |
| First Response Time | ~2.5s | ~3.5s | +1.0s |
| Retention Rate | ~55% | ~72% | +17% |

**Giải thích:**
- Accuracy tăng nhờ routing đúng tool (không bị lẫn policy vào catalog answer)
- Grounded rate tăng nhờ retrieval agent chuyên biệt cho từng collection
- Handoff chính xác hơn nhờ multi-factor decision thay vì confidence đơn thuần
- Latency tăng ~1s do thêm 1-2 LLM calls, đánh đổi chấp nhận được
- Retention tăng nhờ câu trả lời chính xác và hữu ích hơn

### 10.2. Đóng góp khoa học

1. **Phương pháp luận:** Framework so sánh định lượng single-pipeline vs multi-agent cho CSKH
2. **Bộ metrics:** 5 tiêu chí đánh giá toàn diện cho AI agent CSKH tiếng Việt
3. **Dữ liệu:** Dataset hội thoại CSKH bánh online 150 mẫu có label
4. **Thực nghiệm:** Kết quả A/B test trên case study thực tế
5. **Công cụ:** Vietnamese Text Normalizer cho ngôn ngữ TMĐT

### 10.3. Đóng góp thực tiễn

1. **Demo system:** AI Agent hoạt động trên Gấu Bakery (https://cake-i8l0.onrender.com)
2. **Open-source:** Code public trên GitHub
3. **Ứng dụng:** SME có thể tự triển khai cho cửa hàng của mình
4. **Tài liệu:** Hướng dẫn triển khai step-by-step


## 11. Hạn chế & Hướng phát triển

### 11.1. Hạn chế của đề tài

| # | Hạn chế | Mức độ | Giải thích |
|---|---------|--------|------------|
| 1 | Dataset nhỏ (150 mẫu) | Trung bình | Đủ cho khóa luận nhưng chưa đủ cho production |
| 2 | Chỉ 1 case study (bánh online) | Trung bình | Kết quả có thể không khái quát hóa sang ngành khác |
| 3 | Không fine-tune model | Thấp | Dùng API LLM, chi phí cao nếu scale lớn |
| 4 | Tiếng Việt normalization chưa hoàn hảo | Trung bình | BARTpho không xử lý được teencode TMĐT |
| 5 | Không có real-time WebSocket trong MVP | Thấp | Polling đủ cho thực nghiệm |
| 6 | Chưa tích hợp đa kênh (Messenger, Zalo) | Cao | Phạm vi khóa luận giới hạn ở web widget |
| 7 | Không đánh giá với người dùng thật (chỉ synthetic dataset) | Cao | Do giới hạn thời gian và quy mô |

### 11.2. Hướng phát triển trong tương lai

#### Ngắn hạn (sau khóa luận)

1. **User study:** Triển khai thực tế trên Gấu Bakery, thu thập feedback khách hàng thật
2. **WebSocket:** Nâng cấp từ polling lên real-time
3. **Multi-channel:** Tích hợp Facebook Messenger API, Zalo OA webhook
4. **Admin dashboard:** Giao diện quản lý hội thoại + thống kê

#### Dài hạn (nghiên cứu tiếp)

1. **Fine-tune SLM:** Fine-tune Qwen2.5-7B hoặc Llama 3.1-8B cho domain CSKH bánh online
2. **Multi-modal:** Xử lý ảnh bánh khách gửi (nhận diện mẫu bánh từ ảnh)
3. **Voice agent:** Tích hợp speech-to-text + text-to-speech cho CSKH qua điện thoại
4. **Proactive agent:** Tự động chào khách, gợi ý dựa trên browsing behavior
5. **Cross-domain:** Áp dụng kiến trúc cho các SME ngành khác (quần áo, mỹ phẩm, đồ gia dụng)
6. **Multi-language:** Hỗ trợ tiếng Anh, tiếng Trung cho khách du lịch
7. **LLM evaluation automation:** Dùng LLM-as-judge để tự động đánh giá chất lượng (GEval, MT-Bench)

---

## 12. Tài liệu tham khảo

### 12.1. Academic Papers

1. Lewis, P., et al. (2020). "Retrieval-Augmented Generation for Knowledge-Intensive NLP Tasks." *NeurIPS 2020*.

2. Wu, Q., et al. (2023). "AutoGen: Enabling Next-Gen LLM Applications via Multi-Agent Conversation." *arXiv:2308.08155*.

3. Hong, S., et al. (2024). "MetaGPT: Meta Programming for Multi-Agent Collaborative Framework." *ICLR 2024*.

4. Chase, H. (2023). "LangChain: Building Applications with LLMs through Composability." *GitHub repository*.

5. LangGraph Team (2024). "LangGraph: Building Stateful, Multi-Actor Applications with LLMs." *LangChain Inc*.

6. Cui, L., et al. (2023). "A Survey on Dialogue Systems for E-commerce." *ACM Computing Surveys*.

7. Nguyen, D. Q., & Nguyen, A. T. (2020). "PhoBERT: Pre-trained Language Models for Vietnamese." *EMNLP 2020 Findings*.

8. Tran, V. K., et al. (2022). "BARTpho: Pre-trained Sequence-to-Sequence Models for Vietnamese." *arXiv:2109.09706*.

9. Liu, N. F., et al. (2024). "Lost in the Middle: How Language Models Use Long Contexts." *TACL 2024*.

### 12.2. Technical References

10. OpenAI. (2024). "GPT-4o-mini: Advancing Cost-Efficient Intelligence." *OpenAI Blog*.

11. Google AI. (2024). "Gemini 1.5 Flash: Optimized for Speed and Efficiency." *Google AI Blog*.

12. Anthropic. (2024). "Claude 3 Haiku: Fast, Affordable, and Capable." *Anthropic Blog*.

13. ChromaDB. (2024). "Chroma: The Open-source AI Application Database." *trychroma.com*.

14. Liu, J. (2024). "LlamaIndex: A Data Framework for LLM Applications." *GitHub repository*.

15. CrewAI Team. (2024). "CrewAI: Framework for Orchestrating Role-Playing AI Agents." *GitHub repository*.

16. hoanganhvu123. (2024). "ShoppingGPT: Multi-Agent Shopping Assistant." *GitHub repository*.

### 12.3. Tiếng Việt NLP Resources

17. Underthesea. (2024). "Vietnamese NLP Toolkit." *GitHub: undertheseanlp/underthesea*.

18. VinAI Research. (2023). "PhoBERT: Pre-trained Language Models for Vietnamese." *VinAI Research Lab*.

19. VietAI. (2023). "BARTpho: Pre-trained Seq2Seq Models for Vietnamese." *VietAI Research*.

### 12.4. Evaluation Frameworks

20. RAGAS Team. (2024). "RAGAS: Automated Evaluation of Retrieval-Augmented Generation." *arXiv:2309.15217*.

21. Zheng, L., et al. (2023). "Judging LLM-as-a-Judge with MT-Bench and Chatbot Arena." *NeurIPS 2023*.

---

> **Trạng thái:** Draft - Chờ Review
> **Người lập:** AI Agent (Cline)
> **Ngày lập:** 2026-07-06
> **Người hướng dẫn:** [TBD]
> **Sinh viên thực hiện:** [TBD]

