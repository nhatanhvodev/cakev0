# Eval Dataset — Gấu Bakery CSKH Agent

`samples.jsonl` là bộ dữ liệu đánh giá (offline eval) cho hệ thống multi-agent
CSKH. Mỗi dòng là 1 JSON object đúng schema định nghĩa trong
`eval/dataset_schema.py`:

```json
{
  "id": "s001",
  "messages": ["câu khách 1", "..."],
  "expected_intent": "<1 trong 11 intent của app.engines.multiagent.router.INTENTS>",
  "expected_handoff": true,
  "ground_truth_answer": "câu trả lời chuẩn để so sánh/chấm điểm",
  "tags": ["teencode" | "no_diacritics" | "edge_case" | "common"]
}
```

`messages` là danh sách các lượt chat của khách trong CÙNG một hội thoại
(đa số intent chỉ có 1 message; `order_create` thường multi-turn: khách nêu
yêu cầu → điền thông tin giao hàng → xác nhận).

Hiện tại `samples.jsonl` có **11 mẫu viết tay**, phủ đủ cả 11 intent (mỗi
intent xuất hiện ít nhất 1 lần). Đây là tập khởi động — mục tiêu cuối cùng
cho luận văn là **150 mẫu**.

## Mục tiêu 150 mẫu — phân bổ theo tỷ lệ intent (thesis plan §6.2)

Tỷ lệ ước tính theo nhóm intent trong thesis plan (`docs/superpowers/plans/ai-cskh-agent-thesis-plan.md` §6.2):

| Nhóm intent | Tỷ lệ | Số mẫu / 150 |
|---|---|---|
| catalog_search | 30% | 45 |
| faq | 20% | 30 |
| order_status + order_create (Action Agent) | 15% | 23 |
| product_recommend | 15% | 22 |
| policy_shipping + policy_payment + policy_return | 10% | 15 |
| complaint | 5% | 8 |
| chitchat | 3% | 4 |
| handoff_request | 2% | 3 |
| **Tổng** | **100%** | **150** |

Chi tiết theo từng intent cụ thể (11 intent trong `INTENTS`):

| Intent | Số mẫu |
|---|---|
| catalog_search | 45 |
| faq | 30 |
| order_status | 13 |
| order_create | 10 |
| product_recommend | 22 |
| policy_shipping | 5 |
| policy_payment | 5 |
| policy_return | 5 |
| complaint | 8 |
| chitchat | 4 |
| handoff_request | 3 |

Ghi chú: `order_status` và `order_create` cùng thuộc Action Agent nên gộp
chung 15% của nhóm, chia lệch nhẹ vì `order_create` thường multi-turn (1 mẫu
= nhiều message) nên tốn công annotate hơn. Nhóm `policy_*` chia đều 3 intent
con từ 10% của "policy" trong thesis plan.

## Tỷ lệ common / edge-case

Trong mỗi intent, giữ tỷ lệ **70% common / 30% edge-case** (câu hỏi mơ hồ,
teencode nặng, thiếu dấu hoàn toàn, câu ghép nhiều ý, câu có lỗi chính tả,
ngữ cảnh multi-turn phức tạp...). Tag `teencode` và `no_diacritics` có thể
đi kèm `common` hoặc `edge_case` tùy mức độ khó hiểu của câu — không phải cứ
viết tắt là edge case, chỉ khi câu thực sự khó phân loại/khó normalize mới
gắn `edge_case`.

## Quy trình build 150 mẫu

1. **10-15 mẫu tay / intent chính** (đã có 11 mẫu khởi động trong
   `samples.jsonl`): người xây dựng tự viết dựa trên kinh nghiệm bán hàng
   thực tế (tin nhắn Messenger/Zalo mẫu, câu hỏi khách thường gặp).

2. **Sinh synthetic bằng LLM** để bù số lượng còn thiếu:

   ```bash
   cd ai-service
   venv\Scripts\python -m eval.generate_synthetic > eval\dataset\_synthetic_raw.jsonl
   ```

   Script loop qua 11 intent × 2 style (`formal` — viết đầy đủ dấu, chuẩn
   mực; `teencode/no-diacritic` — viết tắt, thiếu dấu) và gọi Gemini sinh 10
   câu mỗi cặp, in JSONL ra stdout. Script **KHÔNG** tự ghi vào
   `samples.jsonl`.

3. **Review tay từng dòng synthetic** trong `_synthetic_raw.jsonl`:
   - Câu có tự nhiên, giống khách hàng thật không?
   - Intent gán có đúng không (đọc lại câu, không tin tưởng mù quáng vào LLM)?
   - `ground_truth_answer` có chính xác theo policy/catalog thật của shop
     không (sửa lại nếu cần)?
   - Gắn `id` duy nhất (ví dụ `syn-catalog_search-teencode-001`), gắn `tags`
     phù hợp (`common`/`edge_case`), set `expected_handoff` đúng (`true`
     chỉ với `complaint`, `handoff_request`, hoặc case đặc biệt cần escalate).
   - Chạy `validate_sample` (qua `load_dataset`) để chắc chắn đúng schema.

4. **Append các dòng đạt yêu cầu** vào `samples.jsonl` (copy/paste thủ công,
   không có script tự động append — tránh đưa dữ liệu chưa review vào eval
   set chính thức).

5. Lặp lại bước 2-4 cho đến khi đạt đủ số lượng mục tiêu theo bảng phân bổ ở
   trên và tỷ lệ 70/30 common/edge-case.

## Annotation & đồng thuận (inter-annotator agreement)

- Toàn bộ 150 mẫu (`expected_intent`, `expected_handoff`, `ground_truth_answer`,
  `tags`) được **2 người annotate độc lập**.
- Sau khi cả 2 xong, so sánh nhãn `expected_intent` giữa 2 người, tính hệ số
  **Cohen's Kappa**. Mục tiêu: **Kappa > 0.8** (đồng thuận gần như tuyệt đối).
- Nếu Kappa ≤ 0.8: 2 người ngồi lại thảo luận từng câu bất đồng, thống nhất
  nhãn cuối cùng, cập nhật lại hướng dẫn annotate (annotation guideline) để
  tránh lặp lại nhầm lẫn tương tự, rồi annotate lại phần còn nghi ngờ.
- Trường hợp vẫn bất đồng sau thảo luận: loại mẫu đó khỏi eval set chính
  thức (đưa vào danh sách "ambiguous — excluded") thay vì ép nhãn.

## Sử dụng

```python
from eval.dataset_schema import load_dataset

samples = load_dataset("eval/dataset/samples.jsonl")  # raise ValueError nếu có dòng sai schema
```
