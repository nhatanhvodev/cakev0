from typing import Protocol
from app.config import Settings


class LLMClient(Protocol):
    def generate(self, system: str, user: str) -> str: ...


class FakeLLM:
    def __init__(self, replies: list[str]):
        self._replies = list(replies)
        self.calls: list[tuple[str, str]] = []

    def generate(self, system: str, user: str) -> str:
        self.calls.append((system, user))
        return self._replies.pop(0) if self._replies else ""


class GeminiClient:
    def __init__(self, settings: Settings):
        from langchain_google_genai import ChatGoogleGenerativeAI
        self._chat = ChatGoogleGenerativeAI(
            model=settings.llm_model,
            google_api_key=settings.gemini_api_key,
            temperature=settings.llm_temperature,
        )

    def generate(self, system: str, user: str) -> str:
        from langchain_core.messages import SystemMessage, HumanMessage
        import time

        try:
            return self._chat.invoke([SystemMessage(system), HumanMessage(user)]).content
        except Exception:
            time.sleep(2)  # spec §11: retry 1 lần rồi mới propagate
            return self._chat.invoke([SystemMessage(system), HumanMessage(user)]).content


def gemini_embed(settings: Settings):
    from langchain_google_genai import GoogleGenerativeAIEmbeddings

    emb = GoogleGenerativeAIEmbeddings(
        model=f"models/{settings.embedding_model}",
        google_api_key=settings.gemini_api_key,
    )
    return lambda texts: emb.embed_documents(list(texts))
