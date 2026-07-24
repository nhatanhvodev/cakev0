from functools import lru_cache
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    engine: str = "multiagent"                 # baseline | multiagent
    gemini_api_key: str = ""
    llm_model: str = "gemini-2.0-flash"
    embedding_model: str = "text-embedding-004"
    llm_temperature: float = 0.3
    chroma_persist_dir: str = "./data/chroma_db"
    mysql_host: str = "127.0.0.1"
    mysql_port: int = 3306
    mysql_user: str = "root"
    mysql_password: str = ""
    mysql_database: str = "banh_store"
    mysql_ssl: bool = False        # enable TLS (required by Aiven / managed MySQL)
    mysql_ssl_ca: str = ""         # optional path to CA cert; verifies server when set
    internal_api_secret: str = "change-me"
    internal_order_api_url: str = "http://localhost/cakev0/api/internal/orders/create.php"
    handoff_confidence_threshold: float = 0.6
    fb_page_token: str = ""
    fb_verify_token: str = ""
    fb_app_secret: str = ""
    cors_origins: str = ""  # required in prod; empty in dev blocks browsers explicitly
    site_base_url: str = "https://cake-i8l0.onrender.com/cakev0"
    enable_normalizer: bool = True

    model_config = {"env_file": ".env", "extra": "ignore"}

@lru_cache
def get_settings() -> Settings:
    return Settings()
