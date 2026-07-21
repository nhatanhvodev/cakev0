# Eval Results

Thư mục này sẽ chứa kết quả chạy `eval/run_eval.py` và bảng phân tích xuất
bởi `eval/analyze.py`, sau khi hoàn thành Task 23 Steps 1-2 (dataset đủ 150
mẫu + chạy eval với Gemini API key thật — chưa thực hiện trong môi trường
sandbox này):

- `baseline.jsonl` — kết quả `run_eval.py --engine baseline`.
- `multiagent.jsonl` — kết quả `run_eval.py --engine multiagent`.
- `multiagent_no_norm.jsonl` — ablation B′ (spec §9), chạy với
  `ENABLE_NORMALIZER=0`.
- `comparison.md` — bảng so sánh M1-M6 hai hệ + delta + p-value, xuất bởi
  `eval/analyze.py`.

Các file này sẽ được commit sau khi chạy để đảm bảo khả năng tái lập
(reproducibility) của kết quả thực nghiệm.
