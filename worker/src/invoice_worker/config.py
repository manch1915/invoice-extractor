from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="WORKER_", env_file=".env", extra="ignore")

    token: str = ""
    ocr_languages: str = "rus+eng"
    max_pages: int = 10
    max_upload_mb: int = 20

    llm_base_url: str = ""
    llm_api_key: str = ""
    llm_model: str = ""
    llm_timeout: float = 60.0
    llm_daily_token_limit: int = 300_000
    llm_budget_file: str = "/tmp/invoice-worker-llm-budget.json"

    @property
    def llm_enabled(self) -> bool:
        return bool(self.llm_base_url and self.llm_api_key and self.llm_model)


@lru_cache
def get_settings() -> Settings:
    return Settings()
